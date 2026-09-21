<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\EventRegistration;

class StaffCheckInService
{
    /** Keep a single staff badge check-in on every confirmed event assignment. */
    public function checkIn(int $participantId, ?int $markedBy = null): void
    {
        EventRegistration::query()
            ->where('participant_id', $participantId)
            ->where('status', EventRegistration::STATUS_CONFIRMED)
            ->whereHas('participant', fn ($query) => $query->where('is_support_staff', true))
            ->with('event')
            ->get()
            ->each(function (EventRegistration $registration) use ($participantId, $markedBy): void {
                Attendance::query()->createOrFirst([
                    'event_id' => $registration->event_id,
                    'participant_id' => $participantId,
                    'day' => -1,
                ], [
                    'status' => 'present',
                    'marked_by' => $markedBy,
                ]);

                app(ApplicationCache::class)->invalidateEvent($registration->event_id, $registration->event->company_id);
            });
    }
}
