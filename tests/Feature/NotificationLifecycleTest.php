<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Notifications\Channels\ArkeselChannel;
use App\Notifications\RegistrationLifecycleNotification;
use App\Services\RegistrationLifecycleService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as NotificationFacade;

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
