<?php

use App\Jobs\SendCustomAttendeeMessageJob;
use App\Models\Company;
use App\Models\CustomMessageRecipient;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\Channels\ArkeselChannel;
use App\Notifications\CustomAttendeeMessage;
use App\Services\PhoneNumberService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
    config(['services.arkesel.enabled' => true]);
});

function customMessageManager(Company $company): User
{
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    return $manager;
}

function customMessageEvent(Company $company): Event
{
    return Event::create(['company_id' => $company->id, 'title' => 'Messaging Event', 'event_date' => now()->addWeek()]);
}

it('classifies Ghana numbers correctly and leaves everything else foreign', function () {
    expect(PhoneNumberService::isGhanaNumber('0241234567'))->toBeTrue()
        ->and(PhoneNumberService::isGhanaNumber('233241234567'))->toBeTrue()
        ->and(PhoneNumberService::isGhanaNumber('+233 24 123 4567'))->toBeTrue()
        ->and(PhoneNumberService::isGhanaNumber('+14155552671'))->toBeFalse()
        ->and(PhoneNumberService::isGhanaNumber('2341234567890'))->toBeFalse()
        ->and(PhoneNumberService::isGhanaNumber(null))->toBeFalse()
        ->and(PhoneNumberService::isGhanaNumber(''))->toBeFalse();

    expect(PhoneNumberService::toArkeselFormat('0241234567'))->toBe('233241234567')
        ->and(PhoneNumberService::toArkeselFormat('+14155552671'))->toBeNull();
});

it('routes Ghana numbers to SMS and foreign numbers to email in smart mode', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Smart Co']);
    $manager = customMessageManager($company);
    $event = customMessageEvent($company);
    $ghana = Participant::create(['company_id' => $company->id, 'name' => 'Local Person', 'email' => 'local@example.com', 'phone' => '0241234567']);
    $foreign = Participant::create(['company_id' => $company->id, 'name' => 'Foreign Person', 'email' => 'foreign@example.com', 'phone' => '+14155552671']);
    $event->registrations()->create(['participant_id' => $ghana->id, 'status' => 'confirmed']);
    $event->registrations()->create(['participant_id' => $foreign->id, 'status' => 'confirmed']);

    $this->actingAs($manager)->post(route('events.messages.store', $event), [
        'subject' => 'Hello',
        'email_body' => "Line one\nLine two",
        'sms_body' => 'Line one',
        'mode' => 'smart',
        'participant_ids' => [$ghana->id, $foreign->id],
    ])->assertRedirect();

    $ghanaRow = CustomMessageRecipient::where('participant_id', $ghana->id)->firstOrFail();
    $foreignRow = CustomMessageRecipient::where('participant_id', $foreign->id)->firstOrFail();

    expect($ghanaRow->channel)->toBe('sms')->and($ghanaRow->status)->toBe('sent')
        ->and($foreignRow->channel)->toBe('mail')->and($foreignRow->status)->toBe('sent');

    Notification::assertSentTo($ghanaRow, CustomAttendeeMessage::class, fn ($notification, $channels) => $channels === [ArkeselChannel::class]);
    Notification::assertSentTo($foreignRow, CustomAttendeeMessage::class, fn ($notification, $channels) => $channels === ['mail']);
});

it('uses the company\'s approved email and SMS sender identities when set', function () {
    Notification::fake();
    $company = Company::create([
        'name' => 'Branded Co',
        'email_from_address' => 'events@brandedco.com',
        'email_from_name' => 'Branded Co Events',
        'email_sender_status' => 'approved',
        'sms_sender_id' => 'BrandedCo',
        'sms_sender_status' => 'approved',
    ]);
    $manager = customMessageManager($company);
    $event = customMessageEvent($company);
    $ghana = Participant::create(['company_id' => $company->id, 'name' => 'Local Person', 'email' => 'local@example.com', 'phone' => '0241234567']);
    $foreign = Participant::create(['company_id' => $company->id, 'name' => 'Foreign Person', 'email' => 'foreign@example.com', 'phone' => '+14155552671']);
    $event->registrations()->create(['participant_id' => $ghana->id, 'status' => 'confirmed']);
    $event->registrations()->create(['participant_id' => $foreign->id, 'status' => 'confirmed']);

    $this->actingAs($manager)->post(route('events.messages.store', $event), [
        'subject' => 'Hello',
        'email_body' => 'Test',
        'sms_body' => 'Test',
        'mode' => 'smart',
        'participant_ids' => [$ghana->id, $foreign->id],
    ])->assertRedirect();

    $ghanaRow = CustomMessageRecipient::where('participant_id', $ghana->id)->firstOrFail();
    $foreignRow = CustomMessageRecipient::where('participant_id', $foreign->id)->firstOrFail();

    Notification::assertSentTo($ghanaRow, CustomAttendeeMessage::class, fn ($notification) => $notification->smsSenderId() === 'BrandedCo');
    Notification::assertSentTo($foreignRow, CustomAttendeeMessage::class, function ($notification, $channels, $notifiable) {
        $mail = $notification->toMail($notifiable);

        return $mail->from === ['events@brandedco.com', 'Branded Co Events'];
    });
});

it('skips foreign numbers entirely in sms-only mode instead of falling back to email', function () {
    Notification::fake();
    $company = Company::create(['name' => 'SMS Only Co']);
    $manager = customMessageManager($company);
    $event = customMessageEvent($company);
    $foreign = Participant::create(['company_id' => $company->id, 'name' => 'Foreign Person', 'email' => 'foreign@example.com', 'phone' => '+14155552671']);
    $event->registrations()->create(['participant_id' => $foreign->id, 'status' => 'confirmed']);

    $this->actingAs($manager)->post(route('events.messages.store', $event), [
        'sms_body' => 'Test',
        'mode' => 'sms_only',
        'participant_ids' => [$foreign->id],
    ])->assertRedirect();

    $row = CustomMessageRecipient::where('participant_id', $foreign->id)->firstOrFail();
    expect($row->channel)->toBeNull()->and($row->status)->toBe('skipped');
    Notification::assertNothingSent();
});

it('forces email for a Ghana number when email-only mode is selected', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Email Only Co']);
    $manager = customMessageManager($company);
    $event = customMessageEvent($company);
    $ghana = Participant::create(['company_id' => $company->id, 'name' => 'Local Person', 'email' => 'local@example.com', 'phone' => '0241234567']);
    $event->registrations()->create(['participant_id' => $ghana->id, 'status' => 'confirmed']);

    $this->actingAs($manager)->post(route('events.messages.store', $event), [
        'subject' => 'Hi',
        'email_body' => 'Test',
        'mode' => 'email_only',
        'participant_ids' => [$ghana->id],
    ])->assertRedirect();

    $row = CustomMessageRecipient::where('participant_id', $ghana->id)->firstOrFail();
    expect($row->channel)->toBe('mail')->and($row->status)->toBe('sent');
});

it('merges and deduplicates recipients selected from registrants and an uploaded csv', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Dedup Co']);
    $manager = customMessageManager($company);
    $event = customMessageEvent($company);
    $participant = Participant::create(['company_id' => $company->id, 'name' => 'Existing Person', 'email' => 'dup@example.com', 'phone' => '0241234567']);
    $event->registrations()->create(['participant_id' => $participant->id, 'status' => 'confirmed']);

    $csv = "Name,Email,Phone\nExisting Person,dup@example.com,0241234567\nNew Person,new@example.com,0551234567\n";
    $file = UploadedFile::fake()->createWithContent('recipients.csv', $csv);

    $this->actingAs($manager)->post(route('events.messages.store', $event), [
        'email_body' => 'Test',
        'sms_body' => 'Test',
        'mode' => 'smart',
        'participant_ids' => [$participant->id],
        'recipients_file' => $file,
    ])->assertRedirect();

    $message = $event->customMessages()->firstOrFail();
    expect($message->recipient_count)->toBe(2)
        ->and($message->recipients()->count())->toBe(2)
        ->and($message->recipients()->where('email', 'new@example.com')->exists())->toBeTrue();
});

it('sends both email and SMS to a Ghana recipient with both channels in "both" mode', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Both Co']);
    $manager = customMessageManager($company);
    $event = customMessageEvent($company);
    $both = Participant::create(['company_id' => $company->id, 'name' => 'Reachable Person', 'email' => 'reachable@example.com', 'phone' => '0241234567']);
    $emailOnlyForeign = Participant::create(['company_id' => $company->id, 'name' => 'Foreign Person', 'email' => 'foreign@example.com', 'phone' => '+14155552671']);
    $event->registrations()->create(['participant_id' => $both->id, 'status' => 'confirmed']);
    $event->registrations()->create(['participant_id' => $emailOnlyForeign->id, 'status' => 'confirmed']);

    $this->actingAs($manager)->post(route('events.messages.store', $event), [
        'subject' => 'Hello',
        'email_body' => 'Email text',
        'sms_body' => 'SMS text',
        'mode' => 'both',
        'participant_ids' => [$both->id, $emailOnlyForeign->id],
    ])->assertRedirect();

    $bothRows = CustomMessageRecipient::where('participant_id', $both->id)->get();
    $foreignRows = CustomMessageRecipient::where('participant_id', $emailOnlyForeign->id)->get();

    expect($bothRows)->toHaveCount(2)
        ->and($bothRows->pluck('channel')->sort()->values()->all())->toBe(['mail', 'sms'])
        ->and($foreignRows)->toHaveCount(1)
        ->and($foreignRows->first()->channel)->toBe('mail');
});

it('never attaches files to an SMS send, only to email', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Attach Co']);
    $manager = customMessageManager($company);
    $event = customMessageEvent($company);
    $both = Participant::create(['company_id' => $company->id, 'name' => 'Reachable Person', 'email' => 'reachable@example.com', 'phone' => '0241234567']);
    $event->registrations()->create(['participant_id' => $both->id, 'status' => 'confirmed']);
    $pdf = UploadedFile::fake()->create('flyer.pdf', 100, 'application/pdf');

    $this->actingAs($manager)->post(route('events.messages.store', $event), [
        'subject' => 'Hello',
        'email_body' => 'Email text',
        'sms_body' => 'SMS text',
        'mode' => 'both',
        'participant_ids' => [$both->id],
        'attachments' => [$pdf],
    ])->assertRedirect();

    $message = $event->customMessages()->firstOrFail();
    expect($message->attachments)->toHaveCount(1)
        ->and($message->attachments[0]['name'])->toBe('flyer.pdf');

    $mailRow = CustomMessageRecipient::where('participant_id', $both->id)->where('channel', 'mail')->firstOrFail();
    $smsRow = CustomMessageRecipient::where('participant_id', $both->id)->where('channel', 'sms')->firstOrFail();

    Notification::assertSentTo($mailRow, CustomAttendeeMessage::class, function ($notification, $channels, $notifiable) {
        return count($notification->toMail($notifiable)->attachments) === 1;
    });
    Notification::assertSentTo($smsRow, CustomAttendeeMessage::class, fn ($notification) => $notification->toArkesel($notification) === 'SMS text');
});

it('sends the manager back to the form with a message when no recipients were chosen', function () {
    $company = Company::create(['name' => 'Empty Co']);
    $manager = customMessageManager($company);
    $event = customMessageEvent($company);

    $this->actingAs($manager)
        ->from(route('events.messages.create', $event))
        ->post(route('events.messages.store', $event), [
            'sms_body' => 'Hello',
            'mode' => 'sms_only',
        ])
        ->assertRedirect(route('events.messages.create', $event))
        ->assertSessionHasErrors('participant_ids');

    expect($event->customMessages()->count())->toBe(0);
});

it('prevents an usher and a cross-company manager from composing messages', function () {
    $company = Company::create(['name' => 'Guarded Co']);
    $otherCompany = Company::create(['name' => 'Other Co']);
    $event = customMessageEvent($company);
    $usher = User::factory()->create(['company_id' => $company->id, 'role' => 'usher']);
    $usher->assignRole('usher');
    $outsideManager = customMessageManager($otherCompany);

    $this->actingAs($usher)->get(route('events.messages.create', $event))->assertForbidden();
    $this->actingAs($outsideManager)->get(route('events.messages.create', $event))->assertForbidden();
});

it('allows a platform admin to compose messages for any company event', function () {
    $company = Company::create(['name' => 'Admin Co']);
    $event = customMessageEvent($company);
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');

    $this->actingAs($admin)->get(route('events.messages.create', $event))->assertOk();
});

it('records a failure without retrying when the mail channel cannot send', function () {
    $company = Company::create(['name' => 'Failing Co']);
    $event = customMessageEvent($company);
    $message = $event->customMessages()->create([
        'company_id' => $company->id,
        'subject' => 'Hi',
        'email_body' => 'Test',
        'mode' => 'smart',
        'recipient_count' => 1,
    ]);
    $recipient = $message->recipients()->create([
        'name' => 'Broken Person',
        'email' => 'person@example.com',
        'channel' => 'mail',
        'status' => 'pending',
    ]);

    // No Notification::fake() here — a real send is attempted (no network call, since
    // an undefined mailer fails immediately) so the job's try/catch has something
    // genuine to catch, proving a failure is recorded rather than silently swallowed
    // or left stuck as "pending".
    config(['mail.default' => 'this_mailer_does_not_exist']);

    (new SendCustomAttendeeMessageJob($recipient->id))->handle();

    $recipient->refresh();
    expect($recipient->status)->toBe('failed')
        ->and($recipient->error_message)->not->toBeNull();
});
