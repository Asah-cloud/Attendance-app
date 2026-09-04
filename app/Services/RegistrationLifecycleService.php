<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Notifications\Concerns\NotifiesPerChannel;
use App\Notifications\RegistrationLifecycleNotification;
use App\Notifications\RoomAssigned;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationLifecycleService
{
    public function __construct(private readonly RoomAllocationService $rooms) {}

    public function approve(EventRegistration $registration): void
    {
        DB::transaction(function () use ($registration): void {
            $event = Event::query()->lockForUpdate()->findOrFail($registration->event_id);
            $confirmed = $event->registrations()->where('status', EventRegistration::STATUS_CONFIRMED)
                ->whereKeyNot($registration->id)->count();

            if ($event->registration_capacity !== null && $confirmed >= $event->registration_capacity) {
                throw ValidationException::withMessages(['registration' => 'This event has reached its confirmed capacity.']);
            }

            $registration->update([
                'status' => EventRegistration::STATUS_CONFIRMED,
                'approved_at' => now(),
                'cancelled_at' => null,
            ]);
        });

        $this->notify($registration, 'approved');
        $this->allocateAccommodation($registration);
    }

    public function reject(EventRegistration $registration): void
    {
        $wasConfirmed = $registration->status === EventRegistration::STATUS_CONFIRMED;
        $registration->update([
            'status' => EventRegistration::STATUS_REJECTED,
            'approved_at' => null,
            'cancelled_at' => null,
        ]);
        $registration->roomAssignment()->where('status', '!=', 'checked_in')->delete();
        $this->notify($registration, 'rejected');

        if ($wasConfirmed) {
            $this->fillAvailablePlaces($registration->event_id);
        }
    }

    public function cancel(EventRegistration $registration): void
    {
        if ($registration->status === EventRegistration::STATUS_CANCELLED) {
            return;
        }

        $wasConfirmed = $registration->status === EventRegistration::STATUS_CONFIRMED;
        $registration->update([
            'status' => EventRegistration::STATUS_CANCELLED,
            'approved_at' => null,
            'cancelled_at' => now(),
        ]);
        $registration->roomAssignment()->where('status', '!=', 'checked_in')->delete();
        $this->notify($registration, 'cancelled');

        if ($wasConfirmed) {
            $this->fillAvailablePlaces($registration->event_id);
        }
    }

    public function capacityChanged(Event $event): void
    {
        $this->fillAvailablePlaces($event->id);
    }

    public function eventChanged(Event $event): void
    {
        $event->registrations()
            ->whereIn('status', [EventRegistration::STATUS_CONFIRMED, EventRegistration::STATUS_PENDING, EventRegistration::STATUS_WAITLISTED])
            ->with('participant')
            ->chunkById(100, function ($registrations): void {
                foreach ($registrations as $registration) {
                    $this->notify($registration, 'event_changed');
                }
            });
    }

    public function cancelEvent(Event $event): void
    {
        if ($event->cancelled_at) {
            return;
        }

        $event->update(['cancelled_at' => now(), 'registration_enabled' => false]);
        $event->registrations()
            ->whereIn('status', [EventRegistration::STATUS_CONFIRMED, EventRegistration::STATUS_PENDING, EventRegistration::STATUS_WAITLISTED])
            ->with('participant')
            ->chunkById(100, function ($registrations): void {
                foreach ($registrations as $registration) {
                    $this->notify($registration, 'event_cancelled');
                }
            });
    }

    public function fillAvailablePlaces(int $eventId): void
    {
        $promoted = DB::transaction(function () use ($eventId) {
            $event = Event::query()->lockForUpdate()->findOrFail($eventId);
            if ($event->registration_capacity === null) {
                $places = PHP_INT_MAX;
            } else {
                $confirmed = $event->registrations()->where('status', EventRegistration::STATUS_CONFIRMED)->count();
                $places = max(0, $event->registration_capacity - $confirmed);
            }

            if ($places === 0) {
                return collect();
            }

            $waiting = $event->registrations()
                ->where('status', EventRegistration::STATUS_WAITLISTED)
                ->oldest('registered_at')
                ->lockForUpdate()
                ->limit($places === PHP_INT_MAX ? 10000 : $places)
                ->get();

            foreach ($waiting as $registration) {
                $registration->update([
                    'status' => EventRegistration::STATUS_CONFIRMED,
                    'approved_at' => now(),
                    'cancelled_at' => null,
                ]);
            }

            return $waiting;
        });

        foreach ($promoted as $registration) {
            $this->notify($registration, 'promoted');
            $this->allocateAccommodation($registration);
        }
    }

    public function allocateAccommodation(EventRegistration $registration): void
    {
        $registration->loadMissing('event');
        if ($registration->status !== EventRegistration::STATUS_CONFIRMED
            || ! $registration->accommodation_required
            || ! $registration->event->accommodation_enabled) {
            return;
        }

        // While attendees are choosing their own rooms, don't pre-assign one — the cutoff job fills the rest.
        if ($registration->event->accommodationSelfSelectOpen()) {
            return;
        }

        // Place just this attendee — a manager's "Assign rooms now" still does the whole-event pass.
        $assignment = $this->rooms->allocateOne($registration);

        if ($assignment && ! $assignment->notification_sent_at && $registration->event->accommodation_published) {
            $assignment->loadMissing(['registration.participant', 'registration.event.company', 'room.floor.block.site']);
            NotifiesPerChannel::send($assignment->registration->participant, new RoomAssigned($assignment));
            $assignment->update(['notification_sent_at' => now()]);
        }
    }

    public function notify(EventRegistration $registration, string $type): void
    {
        $registration->loadMissing(['event.company', 'participant']);
        NotifiesPerChannel::send($registration->participant, new RegistrationLifecycleNotification($registration, $type));
    }
}
