<?php

use App\Models\Company;
use App\Models\CustomMessage;
use App\Models\CustomMessageRecipient;
use App\Models\Event;
use App\Models\MessageTemplate;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\CustomAttendeeMessage;
use App\Services\CustomMessageSender;
use App\Support\MergeFields;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
    config(['services.arkesel.enabled' => true]);
});

function draftsManager(Company $company): User
{
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    return $manager;
}

function draftsSetup(): array
{
    $company = Company::create(['name' => 'Grace Church']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Youth Camp', 'event_date' => now()->addWeek()]);
    unlockAllEventFeatures($event);
    $ama = Participant::create(['company_id' => $company->id, 'name' => 'Ama Mensah', 'email' => 'ama@example.com', 'phone' => '0241234567']);
    $john = Participant::create(['company_id' => $company->id, 'name' => 'John Smith', 'email' => 'john@example.com', 'phone' => '+14155552671']);
    foreach ([$ama, $john] as $person) {
        $event->registrations()->create(['participant_id' => $person->id, 'status' => 'confirmed']);
    }

    return [$company, draftsManager($company), $event, $ama, $john];
}

function futureTime(int $hours = 3): string
{
    return now()->addHours($hours)->format('Y-m-d\TH:i');
}

// ---------------------------------------------------------------- merge fields

it('fills in merge fields per person and leaves unknown ones as typed', function () {
    $values = MergeFields::values('Ama Serwaa Mensah', 'Youth Camp', 'Grace Church');

    expect(MergeFields::render('Hi {first_name}, {name} - {event} by {organization}', $values))
        ->toBe('Hi Ama, Ama Serwaa Mensah - Youth Camp by Grace Church')
        ->and(MergeFields::render('{ NAME } and {First_Name}', $values))->toBe('Ama Serwaa Mensah and Ama')
        ->and(MergeFields::render('Hello {nickname}', $values))->toBe('Hello {nickname}')
        ->and(MergeFields::render(null, $values))->toBe('')
        ->and(MergeFields::render('Hi {first_name}', MergeFields::values('', 'E', 'O')))->toBe('Hi there')
        ->and(MergeFields::unknown('Hi {name} {nickname} {Foo_Bar}'))->toBe(['{nickname}', '{Foo_Bar}']);
});

it('personalises the email and the text message for each recipient', function () {
    Notification::fake();
    [, $manager, $event, $ama, $john] = draftsSetup();

    $this->actingAs($manager)->post(route('events.messages.store', $event), [
        'intent' => 'send',
        'subject' => 'Camp news, {first_name}',
        'email_body' => "Dear {name},\nSee you at {event} — {organization}",
        'sms_body' => 'Hi {first_name}, {event} starts soon',
        'mode' => 'smart',
        'participant_ids' => [$ama->id, $john->id],
    ])->assertRedirect();

    $smsRow = CustomMessageRecipient::where('participant_id', $ama->id)->firstOrFail();
    $mailRow = CustomMessageRecipient::where('participant_id', $john->id)->firstOrFail();

    Notification::assertSentTo($smsRow, CustomAttendeeMessage::class, fn ($notification, $channels, $notifiable) => $notification->toArkesel($notifiable) === 'Hi Ama, Youth Camp starts soon');
    Notification::assertSentTo($mailRow, CustomAttendeeMessage::class, function ($notification, $channels, $notifiable) {
        $mail = $notification->toMail($notifiable);
        $html = (string) $mail->render();

        return $mail->subject === 'Camp news, John'
            && str_contains($html, 'Dear John Smith,')
            && str_contains($html, 'See you at Youth Camp — Grace Church');
    });
});

// ---------------------------------------------------------------- drafts

it('saves a draft without recipients and without sending anything', function () {
    Notification::fake();
    [, $manager, $event] = draftsSetup();

    $this->actingAs($manager)->post(route('events.messages.store', $event), [
        'intent' => 'draft',
        'subject' => 'Work in progress',
        'mode' => 'smart',
    ])->assertRedirect();

    $draft = $event->customMessages()->firstOrFail();
    expect($draft->status)->toBe('draft')
        ->and($draft->subject)->toBe('Work in progress')
        ->and($draft->recipients()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('refuses to save a completely empty draft', function () {
    [, $manager, $event] = draftsSetup();

    $this->actingAs($manager)->from(route('events.messages.index', $event))
        ->post(route('events.messages.store', $event), ['intent' => 'draft', 'mode' => 'smart'])
        ->assertRedirect(route('events.messages.index', $event))
        ->assertSessionHasErrors('email_body');

    expect($event->customMessages()->count())->toBe(0);
});

it('autosaves a draft while typing, updating the same draft each time', function () {
    [, $manager, $event, $ama] = draftsSetup();

    $first = $this->actingAs($manager)->postJson(route('events.messages.autosave', $event), [
        'subject' => 'Half written',
        'mode' => 'smart',
        'participant_ids' => [$ama->id],
    ])->assertOk()->assertJson(['saved' => true])->json();

    $this->actingAs($manager)->postJson(route('events.messages.autosave', $event), [
        'draft_id' => $first['id'],
        'subject' => 'Half written',
        'email_body' => 'Now with a body',
        'mode' => 'both',
        'participant_ids' => [$ama->id],
    ])->assertOk()->assertJson(['saved' => true, 'id' => $first['id']]);

    $draft = $event->customMessages()->firstOrFail();
    expect($event->customMessages()->count())->toBe(1)
        ->and($draft->email_body)->toBe('Now with a body')
        ->and($draft->mode)->toBe('both')
        ->and($draft->draftParticipantIds())->toBe([$ama->id])
        ->and($draft->recipient_count)->toBe(1);
});

it('does not create an empty draft from an empty autosave', function () {
    [, $manager, $event] = draftsSetup();

    $this->actingAs($manager)->postJson(route('events.messages.autosave', $event), ['mode' => 'smart', 'participant_ids' => []])
        ->assertOk()->assertJson(['saved' => false]);

    expect($event->customMessages()->count())->toBe(0);
});

it('never lets an autosave overwrite a message that has already been sent or scheduled', function () {
    [, $manager, $event] = draftsSetup();
    $sent = $event->customMessages()->create(['company_id' => $event->company_id, 'status' => 'sent', 'email_body' => 'Original', 'mode' => 'smart', 'recipient_count' => 0]);
    $scheduled = $event->customMessages()->create(['company_id' => $event->company_id, 'status' => 'scheduled', 'scheduled_at' => now()->addDay(), 'email_body' => 'Original', 'mode' => 'smart', 'recipient_count' => 0]);

    foreach ([$sent, $scheduled] as $message) {
        $this->actingAs($manager)->postJson(route('events.messages.autosave', $event), ['draft_id' => $message->id, 'email_body' => 'Changed', 'mode' => 'smart'])
            ->assertStatus(409);
        expect($message->fresh()->email_body)->toBe('Original');
    }
});

it('turns the autosaved draft into the sent message instead of creating a duplicate', function () {
    Notification::fake();
    [, $manager, $event, $ama] = draftsSetup();

    $draftId = $this->actingAs($manager)->postJson(route('events.messages.autosave', $event), [
        'subject' => 'Camp', 'email_body' => 'Body', 'mode' => 'email_only', 'participant_ids' => [$ama->id],
    ])->json('id');

    $this->actingAs($manager)->post(route('events.messages.store', $event), [
        'intent' => 'send', 'draft_id' => $draftId, 'subject' => 'Camp', 'email_body' => 'Body final', 'mode' => 'email_only', 'participant_ids' => [$ama->id],
    ])->assertRedirect(route('events.messages.index', ['event' => $event, 'message' => $draftId]));

    $message = $event->customMessages()->firstOrFail();
    expect($event->customMessages()->count())->toBe(1)
        ->and($message->id)->toBe($draftId)
        ->and($message->status)->toBe('sent')
        ->and($message->sent_at)->not->toBeNull()
        ->and($message->email_body)->toBe('Body final')
        ->and($message->recipients()->count())->toBe(1)
        ->and($message->draft_recipients)->toBeNull();
});

it('opens a saved draft prefilled and saves changes to it in place', function () {
    [, $manager, $event, $ama] = draftsSetup();
    $draft = $event->customMessages()->create([
        'company_id' => $event->company_id, 'status' => 'draft', 'subject' => 'Saved subject', 'email_body' => 'Saved body', 'mode' => 'smart',
        'draft_recipients' => ['participants' => [$ama->id], 'extras' => []], 'recipient_count' => 1,
    ]);

    $this->actingAs($manager)->get(route('events.messages.edit', [$event, $draft]))
        ->assertOk()->assertSee('Edit draft')->assertSee('Saved subject')->assertSee('Saved body')
        ->assertSee(route('events.messages.update', [$event, $draft]), false);

    $this->actingAs($manager)->put(route('events.messages.update', [$event, $draft]), [
        'intent' => 'draft', 'subject' => 'Changed subject', 'email_body' => 'Saved body', 'mode' => 'smart', 'participant_ids' => [$ama->id],
    ])->assertRedirect();

    expect($event->customMessages()->count())->toBe(1)
        ->and($draft->fresh()->subject)->toBe('Changed subject')
        ->and($draft->fresh()->status)->toBe('draft');
});

it('lists drafts and scheduled messages under their own filters, apart from sent ones', function () {
    [, $manager, $event] = draftsSetup();
    $event->customMessages()->create(['company_id' => $event->company_id, 'status' => 'draft', 'subject' => 'A draft note', 'email_body' => 'x', 'mode' => 'smart', 'recipient_count' => 0]);
    $event->customMessages()->create(['company_id' => $event->company_id, 'status' => 'scheduled', 'scheduled_at' => now()->addDay(), 'subject' => 'A scheduled note', 'email_body' => 'x', 'mode' => 'smart', 'recipient_count' => 0]);
    $event->customMessages()->create(['company_id' => $event->company_id, 'status' => 'sent', 'sent_at' => now(), 'subject' => 'A sent note', 'email_body' => 'x', 'mode' => 'smart', 'recipient_count' => 0]);

    $this->actingAs($manager)->get(route('events.messages.index', $event))
        ->assertOk()->assertSee('A sent note')->assertSee('A scheduled note')->assertDontSee('A draft note');
    $this->actingAs($manager)->get(route('events.messages.index', ['event' => $event, 'filter' => 'drafts']))
        ->assertOk()->assertSee('A draft note')->assertDontSee('A sent note')->assertDontSee('A scheduled note');
    $this->actingAs($manager)->get(route('events.messages.index', ['event' => $event, 'filter' => 'scheduled']))
        ->assertOk()->assertSee('A scheduled note')->assertDontSee('A sent note')->assertDontSee('A draft note');
});

it('shows a draft and a scheduled message expanded with their actions instead of delivery figures', function () {
    [, $manager, $event, $ama] = draftsSetup();
    $draft = $event->customMessages()->create(['company_id' => $event->company_id, 'status' => 'draft', 'email_body' => 'Draft text', 'mode' => 'smart', 'draft_recipients' => ['participants' => [$ama->id], 'extras' => [['name' => 'Kofi Extra', 'email' => 'k@example.com', 'phone' => null]]], 'recipient_count' => 2]);
    $scheduled = $event->customMessages()->create(['company_id' => $event->company_id, 'status' => 'scheduled', 'scheduled_at' => now()->addDay(), 'email_body' => 'Later text', 'mode' => 'smart', 'draft_recipients' => ['participants' => [$ama->id], 'extras' => []], 'recipient_count' => 1]);

    $this->actingAs($manager)->get(route('events.messages.index', ['event' => $event, 'message' => $draft->id]))
        ->assertOk()->assertSee('Continue editing')->assertSee('Send now')->assertSee('Ama Mensah')->assertSee('Kofi Extra')
        ->assertDontSee('Cancel schedule')->assertDontSee('Recipients', false);
    $this->actingAs($manager)->get(route('events.messages.index', ['event' => $event, 'message' => $scheduled->id]))
        ->assertOk()->assertSee('Scheduled for')->assertSee('Cancel schedule');
});

it('keeps or drops the people from a saved upload depending on what the edit page showed', function () {
    Notification::fake();
    [, $manager, $event] = draftsSetup();
    $csv = "Name,Email,Phone\nKofi Extra,kofi@example.com,0551234567\nAkua Extra,akua@example.com,0201112222\n";

    $this->actingAs($manager)->post(route('events.messages.store', $event), [
        'intent' => 'draft', 'email_body' => 'Hello', 'mode' => 'email_only',
        'recipients_file' => UploadedFile::fake()->createWithContent('list.csv', $csv),
    ])->assertRedirect();
    $draft = $event->customMessages()->firstOrFail();
    expect($draft->draftExtras())->toHaveCount(2);

    $this->actingAs($manager)->get(route('events.messages.edit', [$event, $draft]))
        ->assertOk()->assertSee('name="seed_shown"', false)->assertSee('name="recipient_keys[]"', false)->assertSee('Kofi Extra');

    // A panel that never listed them (no seed_shown) must not silently lose them.
    $this->actingAs($manager)->put(route('events.messages.update', [$event, $draft]), ['intent' => 'draft', 'email_body' => 'Hello again', 'mode' => 'email_only'])->assertRedirect();
    expect($draft->fresh()->draftExtras())->toHaveCount(2);

    // The edit page lists them; leaving only the first ticked keeps just that one.
    $this->actingAs($manager)->put(route('events.messages.update', [$event, $draft]), ['intent' => 'draft', 'email_body' => 'Hello again', 'mode' => 'email_only', 'seed_shown' => 1, 'recipient_keys' => [0]])->assertRedirect();
    expect(array_column($draft->fresh()->draftExtras(), 'name'))->toBe(['Kofi Extra']);
});

// ---------------------------------------------------------------- scheduling

it('schedules a message for later without sending or creating deliveries yet', function () {
    Notification::fake();
    [, $manager, $event, $ama] = draftsSetup();
    $when = futureTime(3);

    $this->actingAs($manager)->post(route('events.messages.store', $event), [
        'intent' => 'schedule', 'scheduled_at' => $when, 'subject' => 'Later', 'email_body' => 'Body', 'mode' => 'email_only', 'participant_ids' => [$ama->id],
    ])->assertRedirect();

    $message = $event->customMessages()->firstOrFail();
    expect($message->status)->toBe('scheduled')
        ->and($message->scheduled_at->format('Y-m-d\TH:i'))->toBe($when)
        ->and($message->recipients()->count())->toBe(0)
        ->and($message->recipient_count)->toBe(1);
    Notification::assertNothingSent();
});

it('rejects a schedule time that is missing, in the past, or too far away', function () {
    [, $manager, $event, $ama] = draftsSetup();
    $base = ['intent' => 'schedule', 'email_body' => 'Body', 'mode' => 'email_only', 'participant_ids' => [$ama->id]];

    foreach ([
        [[], 'scheduled_at'],
        [['scheduled_at' => now()->subHour()->format('Y-m-d\TH:i')], 'scheduled_at'],
        [['scheduled_at' => now()->addSeconds(20)->format('Y-m-d\TH:i')], 'scheduled_at'],
        [['scheduled_at' => now()->addYears(2)->format('Y-m-d\TH:i')], 'scheduled_at'],
        [['scheduled_at' => 'not a date'], 'scheduled_at'],
    ] as [$extra, $errorKey]) {
        $this->actingAs($manager)->post(route('events.messages.store', $event), $base + $extra)->assertSessionHasErrors($errorKey);
    }

    expect($event->customMessages()->count())->toBe(0);
});

it('needs recipients to schedule but not to save a draft', function () {
    [, $manager, $event] = draftsSetup();

    $this->actingAs($manager)->post(route('events.messages.store', $event), [
        'intent' => 'schedule', 'scheduled_at' => futureTime(), 'email_body' => 'Body', 'mode' => 'smart',
    ])->assertSessionHasErrors('participant_ids');
});

it('sends a scheduled message once its time comes, and only once', function () {
    Notification::fake();
    [, $manager, $event, $ama, $john] = draftsSetup();

    $this->actingAs($manager)->post(route('events.messages.store', $event), [
        'intent' => 'schedule', 'scheduled_at' => futureTime(2), 'subject' => 'Timed', 'email_body' => 'Hello {first_name}', 'sms_body' => 'Hi {first_name}', 'mode' => 'smart', 'participant_ids' => [$ama->id, $john->id],
    ])->assertRedirect();
    $message = $event->customMessages()->firstOrFail();

    expect(app(CustomMessageSender::class)->dispatchDue())->toBe(0);
    Notification::assertNothingSent();

    $this->travel(3)->hours();

    expect(app(CustomMessageSender::class)->dispatchDue())->toBe(1);
    $message->refresh();
    expect($message->status)->toBe('sent')
        ->and($message->sent_at)->not->toBeNull()
        ->and($message->recipients()->count())->toBe(2);
    Notification::assertSentTimes(CustomAttendeeMessage::class, 2);

    expect(app(CustomMessageSender::class)->dispatchDue())->toBe(0);
    Notification::assertSentTimes(CustomAttendeeMessage::class, 2);
});

it('uses contact details as they are when a scheduled message goes out', function () {
    Notification::fake();
    [, $manager, $event, $ama] = draftsSetup();
    $this->actingAs($manager)->post(route('events.messages.store', $event), [
        'intent' => 'schedule', 'scheduled_at' => futureTime(1), 'email_body' => 'Hello', 'mode' => 'email_only', 'participant_ids' => [$ama->id],
    ])->assertRedirect();

    $ama->update(['email' => 'ama.new@example.com']);
    $this->travel(2)->hours();
    app(CustomMessageSender::class)->dispatchDue();

    expect(CustomMessageRecipient::firstOrFail()->email)->toBe('ama.new@example.com');
});

it('hands a scheduled message back as a draft when nobody is left to send it to', function () {
    Notification::fake();
    [, $manager, $event, $ama] = draftsSetup();
    $this->actingAs($manager)->post(route('events.messages.store', $event), [
        'intent' => 'schedule', 'scheduled_at' => futureTime(1), 'email_body' => 'Hello', 'mode' => 'email_only', 'participant_ids' => [$ama->id],
    ])->assertRedirect();
    $message = $event->customMessages()->firstOrFail();

    $ama->registrations()->delete();
    $ama->delete();
    $this->travel(2)->hours();

    expect(app(CustomMessageSender::class)->dispatchDue())->toBe(0);
    expect($message->fresh()->status)->toBe('draft')->and($message->fresh()->scheduled_at)->toBeNull();
    Notification::assertNothingSent();
});

it('runs the scheduled-message sender every minute', function () {
    $names = collect(app(Schedule::class)->events())->map(fn ($event) => $event->description)->all();

    expect($names)->toContain('send-scheduled-messages');
});

it('can send a draft or scheduled message immediately', function () {
    Notification::fake();
    [, $manager, $event, $ama] = draftsSetup();
    $draft = $event->customMessages()->create(['company_id' => $event->company_id, 'status' => 'draft', 'email_body' => 'Go', 'mode' => 'email_only', 'draft_recipients' => ['participants' => [$ama->id], 'extras' => []], 'recipient_count' => 1]);

    $this->actingAs($manager)->post(route('events.messages.send-now', [$event, $draft]))
        ->assertRedirect(route('events.messages.index', ['event' => $event, 'message' => $draft->id]));

    expect($draft->fresh()->status)->toBe('sent')->and($draft->recipients()->count())->toBe(1);
    Notification::assertSentTimes(CustomAttendeeMessage::class, 1);

    // Already sent: pressing it again must not send a second time.
    $this->actingAs($manager)->post(route('events.messages.send-now', [$event, $draft]))->assertNotFound();
    Notification::assertSentTimes(CustomAttendeeMessage::class, 1);
});

it('will not send a draft that has no recipients', function () {
    Notification::fake();
    [, $manager, $event] = draftsSetup();
    $draft = $event->customMessages()->create(['company_id' => $event->company_id, 'status' => 'draft', 'email_body' => 'Go', 'mode' => 'email_only', 'recipient_count' => 0]);

    $this->actingAs($manager)->post(route('events.messages.send-now', [$event, $draft]))->assertSessionHas('error');

    expect($draft->fresh()->status)->toBe('draft');
    Notification::assertNothingSent();
});

it('can cancel a schedule, turning the message back into a draft', function () {
    [, $manager, $event] = draftsSetup();
    $message = $event->customMessages()->create(['company_id' => $event->company_id, 'status' => 'scheduled', 'scheduled_at' => now()->addDay(), 'email_body' => 'Go', 'mode' => 'smart', 'recipient_count' => 0]);

    $this->actingAs($manager)->post(route('events.messages.unschedule', [$event, $message]))->assertRedirect();

    expect($message->fresh()->status)->toBe('draft')->and($message->fresh()->scheduled_at)->toBeNull();
});

it('deletes a draft and its attachments, but never a sent message', function () {
    Storage::fake('local');
    [, $manager, $event] = draftsSetup();
    $draft = $event->customMessages()->create(['company_id' => $event->company_id, 'status' => 'draft', 'email_body' => 'x', 'mode' => 'smart', 'recipient_count' => 0]);
    Storage::disk('local')->put("custom-message-attachments/{$draft->id}/file.pdf", 'pdf');
    $sent = $event->customMessages()->create(['company_id' => $event->company_id, 'status' => 'sent', 'sent_at' => now(), 'email_body' => 'x', 'mode' => 'smart', 'recipient_count' => 0]);

    $this->actingAs($manager)->delete(route('events.messages.destroy', [$event, $sent]))->assertNotFound();
    expect(CustomMessage::find($sent->id))->not->toBeNull();

    $this->actingAs($manager)->delete(route('events.messages.destroy', [$event, $draft]))->assertRedirect();
    expect(CustomMessage::find($draft->id))->toBeNull();
    Storage::disk('local')->assertMissing("custom-message-attachments/{$draft->id}/file.pdf");
});

it('keeps drafts, schedules and templates away from other companies and ushers', function () {
    [, , $event] = draftsSetup();
    $outsider = draftsManager(Company::create(['name' => 'Other Co']));
    $usher = User::factory()->create(['company_id' => $event->company_id, 'role' => 'usher']);
    $usher->assignRole('usher');
    $draft = $event->customMessages()->create(['company_id' => $event->company_id, 'status' => 'draft', 'email_body' => 'x', 'mode' => 'smart', 'recipient_count' => 0]);
    $template = MessageTemplate::create(['company_id' => $event->company_id, 'name' => 'Mine', 'email_body' => 'x']);

    foreach ([$outsider, $usher] as $user) {
        $this->actingAs($user)->postJson(route('events.messages.autosave', $event), ['email_body' => 'x', 'mode' => 'smart'])->assertForbidden();
        $this->actingAs($user)->put(route('events.messages.update', [$event, $draft]), ['intent' => 'draft', 'email_body' => 'x', 'mode' => 'smart'])->assertForbidden();
        $this->actingAs($user)->post(route('events.messages.send-now', [$event, $draft]))->assertForbidden();
        $this->actingAs($user)->delete(route('events.messages.destroy', [$event, $draft]))->assertForbidden();
        $this->actingAs($user)->postJson(route('events.message-templates.store', $event), ['name' => 'Nope', 'email_body' => 'x'])->assertForbidden();
        $this->actingAs($user)->deleteJson(route('events.message-templates.destroy', [$event, $template]))->assertForbidden();
    }

    expect(CustomMessage::find($draft->id))->not->toBeNull()->and(MessageTemplate::find($template->id))->not->toBeNull();
});

// ---------------------------------------------------------------- templates

it('saves, updates, lists and deletes reusable templates for the company', function () {
    [$company, $manager, $event] = draftsSetup();

    $created = $this->actingAs($manager)->postJson(route('events.message-templates.store', $event), [
        'name' => 'Event reminder', 'subject' => 'See you soon, {first_name}', 'email_body' => 'Body', 'sms_body' => 'Text',
    ])->assertOk()->assertJson(['name' => 'Event reminder', 'updated' => false])->json();

    $this->actingAs($manager)->postJson(route('events.message-templates.store', $event), [
        'name' => 'Event reminder', 'email_body' => 'Better body',
    ])->assertOk()->assertJson(['id' => $created['id'], 'updated' => true]);

    expect(MessageTemplate::where('company_id', $company->id)->count())->toBe(1)
        ->and(MessageTemplate::first()->email_body)->toBe('Better body');

    $this->actingAs($manager)->get(route('events.messages.create', $event))
        ->assertOk()->assertSee('Event reminder');

    $this->actingAs($manager)->deleteJson(route('events.message-templates.destroy', [$event, $created['id']]))->assertOk();
    expect(MessageTemplate::count())->toBe(0);
});

it('requires a body to save a template and shares templates only within a company', function () {
    [$company, $manager, $event] = draftsSetup();
    $otherCompany = Company::create(['name' => 'Other Co']);
    $foreign = MessageTemplate::create(['company_id' => $otherCompany->id, 'name' => 'Theirs', 'email_body' => 'secret']);

    $this->actingAs($manager)->postJson(route('events.message-templates.store', $event), ['name' => 'Empty'])->assertStatus(422);
    $this->actingAs($manager)->postJson(route('events.message-templates.store', $event), ['name' => str_repeat('x', 81), 'email_body' => 'Body'])->assertStatus(422);

    $this->actingAs($manager)->deleteJson(route('events.message-templates.destroy', [$event, $foreign]))->assertNotFound();
    expect(MessageTemplate::find($foreign->id))->not->toBeNull();

    $this->actingAs($manager)->get(route('events.messages.create', $event))->assertOk()->assertDontSee('Theirs');
});
