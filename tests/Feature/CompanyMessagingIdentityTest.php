<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\Channels\ArkeselChannel;
use App\Notifications\EventRegistrationSubmitted;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager'] as $role) {
        Role::findOrCreate($role);
    }
});

function messagingManager(Company $company): User
{
    $manager = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'manager',
        'email_verified_at' => now(),
    ]);
    $manager->assignRole('manager');

    return $manager;
}

function messagingNotification(Company $company): array
{
    $event = Event::create([
        'company_id' => $company->id,
        'title' => 'Company Event',
        'event_date' => now()->addDay(),
    ]);
    $participant = Participant::create([
        'company_id' => $company->id,
        'name' => 'Guest',
        'email' => 'guest@example.com',
        'phone' => '0201234567',
    ]);
    $registration = EventRegistration::create([
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'status' => EventRegistration::STATUS_CONFIRMED,
    ]);

    return [$participant, new EventRegistrationSubmitted($registration)];
}

it('lets a manager request messaging identities and marks changed values pending', function () {
    $company = Company::create(['name' => 'Acme']);

    $this->actingAs(messagingManager($company))
        ->patch(route('organization.messaging.update'), [
            'email_from_name' => 'Acme Events',
            'email_from_address' => 'events@acme.test',
            'sms_sender_id' => 'ACME',
        ])
        ->assertRedirect();

    expect($company->fresh())
        ->email_sender_status->toBe('pending')
        ->sms_sender_status->toBe('pending')
        ->email_from_address->toBe('events@acme.test')
        ->sms_sender_id->toBe('ACME');
});

it('uses an approved company email identity and otherwise keeps the platform default', function () {
    $company = Company::create([
        'name' => 'Acme',
        'email_from_name' => 'Acme Events',
        'email_from_address' => 'events@acme.test',
        'email_sender_status' => 'approved',
    ]);
    [$participant, $notification] = messagingNotification($company);

    expect($notification->toMail($participant)->from)
        ->toBe(['events@acme.test', 'Acme Events']);

    $company->update(['email_sender_status' => 'pending']);
    [, $pendingNotification] = messagingNotification($company);
    expect($pendingNotification->toMail($participant)->from)->toBeEmpty();
});

it('uses an approved company SMS sender ID and falls back while pending', function () {
    config()->set('services.arkesel', [
        'enabled' => true,
        'key' => 'test-key',
        'sender' => 'Attendance',
        'url' => 'https://sms.arkesel.test/api/v2/sms/send',
        'callback_url' => null,
        'sandbox' => true,
    ]);
    Http::fake(['sms.arkesel.test/*' => Http::response(['status' => 'success'], 200)]);

    $company = Company::create(['name' => 'Acme', 'sms_sender_id' => 'ACME', 'sms_sender_status' => 'approved']);
    [$participant, $notification] = messagingNotification($company);
    app(ArkeselChannel::class)->send($participant, $notification);
    Http::assertSent(fn ($request) => $request['sender'] === 'ACME');

    $company->update(['sms_sender_status' => 'pending']);
    [, $pendingNotification] = messagingNotification($company);
    app(ArkeselChannel::class)->send($participant, $pendingNotification);
    Http::assertSent(fn ($request) => $request['sender'] === 'Attendance');
});
