<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\ArkeselBalanceLow;
use App\Notifications\Channels\ArkeselChannel;
use App\Notifications\RegistrationLifecycleNotification;
use App\Services\RegistrationLifecycleService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Spatie\Permission\Models\Role;

function lifecycleEvent(?int $capacity = 1): Event
{
    $company = Company::create(['name' => 'Lifecycle Company']);

    return Event::create([
        'company_id' => $company->id,
        'title' => 'Lifecycle Event',
        'event_date' => now()->addWeek(),
        'registration_capacity' => $capacity,
        'registration_enabled' => true,
    ]);
}

function lifecycleRegistration(Event $event, string $name, string $status): EventRegistration
{
    $participant = Participant::create([
        'company_id' => $event->company_id,
        'name' => $name,
        'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
        'phone' => '0201234567',
    ]);

    return $event->registrations()->create([
        'participant_id' => $participant->id,
        'status' => $status,
        'approved_at' => $status === EventRegistration::STATUS_CONFIRMED ? now() : null,
    ]);
}

it('promotes the oldest waitlisted attendee when a confirmed attendee cancels', function () {
    NotificationFacade::fake();
    $event = lifecycleEvent();
    $confirmed = lifecycleRegistration($event, 'Confirmed Person', EventRegistration::STATUS_CONFIRMED);
    $first = lifecycleRegistration($event, 'First Waiting', EventRegistration::STATUS_WAITLISTED);
    $second = lifecycleRegistration($event, 'Second Waiting', EventRegistration::STATUS_WAITLISTED);
    $first->update(['registered_at' => now()->subMinute()]);

    app(RegistrationLifecycleService::class)->cancel($confirmed);

    expect($confirmed->fresh()->status)->toBe(EventRegistration::STATUS_CANCELLED)
        ->and($first->fresh()->status)->toBe(EventRegistration::STATUS_CONFIRMED)
        ->and($second->fresh()->status)->toBe(EventRegistration::STATUS_WAITLISTED);

    NotificationFacade::assertSentTo($confirmed->participant, RegistrationLifecycleNotification::class,
        fn ($notification) => $notification->type === 'cancelled');
    NotificationFacade::assertSentTo($first->participant, RegistrationLifecycleNotification::class,
        fn ($notification) => $notification->type === 'promoted');
});

it('promotes waitlisted attendees when capacity increases', function () {
    NotificationFacade::fake();
    $event = lifecycleEvent(1);
    lifecycleRegistration($event, 'Confirmed Person', EventRegistration::STATUS_CONFIRMED);
    $waiting = lifecycleRegistration($event, 'Waiting Person', EventRegistration::STATUS_WAITLISTED);
    $event->update(['registration_capacity' => 2]);

    app(RegistrationLifecycleService::class)->capacityChanged($event);

    expect($waiting->fresh()->status)->toBe(EventRegistration::STATUS_CONFIRMED);
});

it('dispatches each eligible channel as its own independently locked notification', function () {
    NotificationFacade::fake();
    $event = lifecycleEvent();
    $registration = lifecycleRegistration($event, 'Both Channels Person', EventRegistration::STATUS_CONFIRMED);

    app(RegistrationLifecycleService::class)->notify($registration, 'confirmed');

    NotificationFacade::assertSentToTimes($registration->participant, RegistrationLifecycleNotification::class, 2);
    NotificationFacade::assertSentTo(
        $registration->participant,
        RegistrationLifecycleNotification::class,
        fn ($notification, $channels) => $channels === ['mail']
    );
    NotificationFacade::assertSentTo(
        $registration->participant,
        RegistrationLifecycleNotification::class,
        fn ($notification, $channels) => $channels === [ArkeselChannel::class]
    );
});

it('sends Arkesel requests with normalized Ghana numbers', function () {
    config()->set('services.arkesel', [
        'enabled' => true,
        'key' => 'test-key',
        'sender' => 'Attendance',
        'url' => 'https://sms.arkesel.test/api/v2/sms/send',
        'callback_url' => 'https://example.com/sms/callback',
        'sandbox' => true,
    ]);
    Http::fake(['sms.arkesel.test/*' => Http::response(['status' => 'success'], 200)]);
    $participant = Participant::create(['name' => 'SMS Person', 'phone' => '020 123 4567']);
    $notification = new class extends Notification
    {
        public function toArkesel(object $notifiable): string
        {
            return 'Test lifecycle message';
        }
    };

    app(ArkeselChannel::class)->send($participant, $notification);

    Http::assertSent(fn ($request) => $request->hasHeader('api-key', 'test-key')
        && $request['recipients'] === ['233201234567']
        && $request['sandbox'] === true);
});

it('alerts admins once when Arkesel reports an insufficient balance, then stays quiet for a while', function () {
    Cache::flush();
    Role::findOrCreate('admin');
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');
    NotificationFacade::fake();

    config()->set('services.arkesel', [
        'enabled' => true,
        'key' => 'test-key',
        'sender' => 'Attendance',
        'url' => 'https://sms.arkesel.test/api/v2/sms/send',
        'callback_url' => null,
        'sandbox' => true,
    ]);
    Http::fake(['sms.arkesel.test/*' => Http::response(['message' => 'Insufficient balance or invalid coverage!', 'status' => 'error'], 402)]);
    $participant = Participant::create(['name' => 'SMS Person', 'phone' => '0201234567']);
    $notification = new class extends Notification
    {
        public function toArkesel(object $notifiable): string
        {
            return 'Test message';
        }
    };

    try {
        app(ArkeselChannel::class)->send($participant, $notification);
    } catch (Throwable) {
        // Expected: Arkesel rejects the request. What matters is the alert below.
    }

    NotificationFacade::assertSentTo($admin, ArkeselBalanceLow::class);

    // A second failure right after shouldn't send a second alert.
    try {
        app(ArkeselChannel::class)->send($participant, $notification);
    } catch (Throwable) {
        //
    }
    NotificationFacade::assertSentToTimes($admin, ArkeselBalanceLow::class, 1);
});
