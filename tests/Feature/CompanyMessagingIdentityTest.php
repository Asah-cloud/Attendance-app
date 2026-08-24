<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\Channels\ArkeselChannel;
use App\Notifications\EmailDomainDnsInstructions;
use App\Notifications\EmailDomainStatusNotification;
use App\Notifications\EventRegistrationSubmitted;
use App\Services\EmailDomainLifecycleManager;
use App\Services\ResendDomainService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
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
    Notification::fake();
    $company = Company::create(['name' => 'Acme']);
    $manager = messagingManager($company);
    $resend = Mockery::mock(ResendDomainService::class);
    $resend->shouldReceive('create')->once()->with('acme.test')->andReturn([
        'id' => 'domain-123',
        'name' => 'acme.test',
        'status' => 'not_started',
        'records' => [[
            'type' => 'TXT',
            'name' => 'send',
            'value' => 'v=spf1 include:amazonses.com ~all',
        ]],
    ]);
    app()->instance(ResendDomainService::class, $resend);

    $this->actingAs($manager)
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
        ->sms_sender_id->toBe('ACME')
        ->resend_domain_id->toBe('domain-123')
        ->resend_domain_name->toBe('acme.test');

    Notification::assertSentTo($manager, EmailDomainDnsInstructions::class);
});

it('shows DNS instructions in the app and approves email only after Resend verifies them', function () {
    Notification::fake();
    $company = Company::create([
        'name' => 'Acme',
        'email_from_address' => 'events@acme.test',
        'email_sender_status' => 'pending',
        'resend_domain_id' => 'domain-123',
        'resend_domain_name' => 'acme.test',
        'resend_domain_status' => 'pending',
        'resend_domain_records' => [[
            'type' => 'CNAME',
            'name' => 'resend._domainkey',
            'value' => 'example.dkim.amazonses.com',
        ]],
    ]);
    $manager = messagingManager($company);

    $this->actingAs($manager)
        ->get(route('organization.branding.edit'))
        ->assertOk()
        ->assertSee('resend._domainkey')
        ->assertSee('example.dkim.amazonses.com');

    $resend = Mockery::mock(ResendDomainService::class);
    $resend->shouldReceive('checkVerification')->once()->with('domain-123')->andReturn([
        'id' => 'domain-123',
        'name' => 'acme.test',
        'status' => 'verified',
        'records' => $company->resend_domain_records,
    ]);
    app()->instance(ResendDomainService::class, $resend);

    $this->actingAs($manager)
        ->post(route('organization.messaging.email-domain.check'))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($company->fresh())
        ->email_sender_status->toBe('approved')
        ->resend_domain_status->toBe('verified');

    Notification::assertSentTo($manager, EmailDomainStatusNotification::class, fn ($notification) => $notification->type === 'verified');
});

it('reminds managers after 24 and 72 hours and notifies them when verification completes', function () {
    Notification::fake();
    $company = Company::create([
        'name' => 'Acme',
        'email_from_address' => 'events@acme.test',
        'email_sender_status' => 'pending',
        'resend_domain_id' => 'domain-123',
        'resend_domain_name' => 'acme.test',
        'resend_domain_status' => 'pending',
        'resend_domain_records' => [],
        'resend_setup_started_at' => now()->subHours(25),
    ]);
    $manager = messagingManager($company);
    $pending = ['id' => 'domain-123', 'name' => 'acme.test', 'status' => 'pending', 'records' => []];
    $verified = ['id' => 'domain-123', 'name' => 'acme.test', 'status' => 'verified', 'records' => []];
    $resend = Mockery::mock(ResendDomainService::class);
    $resend->shouldReceive('checkVerification')->times(3)->andReturn($pending, $pending, $verified);
    $lifecycle = new EmailDomainLifecycleManager($resend);

    $lifecycle->processPending();
    Notification::assertSentTo($manager, EmailDomainStatusNotification::class, fn ($notification) => $notification->type === 'reminder');

    $company->update(['resend_setup_started_at' => now()->subHours(73)]);
    $lifecycle->processPending();
    Notification::assertSentTo($manager, EmailDomainStatusNotification::class, fn ($notification) => $notification->type === 'delayed');

    $lifecycle->processPending();
    Notification::assertSentTo($manager, EmailDomainStatusNotification::class, fn ($notification) => $notification->type === 'verified');
    expect($company->fresh()->email_sender_status)->toBe('approved');
});

it('notifies managers once when Resend reports a verification failure', function () {
    Notification::fake();
    $company = Company::create([
        'name' => 'Acme',
        'email_from_address' => 'events@acme.test',
        'email_sender_status' => 'pending',
        'resend_domain_id' => 'domain-123',
        'resend_domain_name' => 'acme.test',
        'resend_domain_status' => 'pending',
        'resend_domain_records' => [],
        'resend_setup_started_at' => now()->subDay(),
    ]);
    $manager = messagingManager($company);
    $failed = ['id' => 'domain-123', 'name' => 'acme.test', 'status' => 'failed', 'records' => []];
    $resend = Mockery::mock(ResendDomainService::class);
    $resend->shouldReceive('checkVerification')->twice()->andReturn($failed);
    $lifecycle = new EmailDomainLifecycleManager($resend);

    $lifecycle->processPending();
    $lifecycle->processPending();

    Notification::assertSentToTimes($manager, EmailDomainStatusNotification::class, 1);
    Notification::assertSentTo($manager, EmailDomainStatusNotification::class, fn ($notification) => $notification->type === 'failed');
    expect($company->fresh()->resend_failure_notice_sent_at)->not->toBeNull();
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
