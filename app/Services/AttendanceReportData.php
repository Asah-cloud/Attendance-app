<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Participant;
use Illuminate\Support\Collection;

class AttendanceReportData
{
    /** @return array{presentUsers: Collection, absentUsers: Collection, totalExpected: int} */
    public function forPeriod(Event $event, int|string $day, ?int $session = null): array
    {
        // A session filter only ever narrows a specific numbered program day (day >= 1);
        // arrival and the "all days" view always mean "present at any session".
        $session = $day === 'all' || (int) $day < 1 ? null : $session;

        $eligible = $day === 'all' || (int) $day === 0
            ? $event->confirmedParticipants()
            : $event->attendanceEligibleParticipants();
        $eligibleIds = $eligible->pluck('participants.id');

        $presentAttendeeIds = Attendance::query()
            ->where('event_id', $event->id)
            ->whereIn('participant_id', $eligibleIds)
            ->when($day === 'all', fn ($query) => $query->where('day', '>=', 1), fn ($query) => $query->where('day', $day))
            ->when($session !== null, fn ($query) => $query->where('session', $session))
            ->distinct()
            ->pluck('participant_id');

        // Numbered participant staff have a persistent staff check-in and are
        // included on every program day, just as they are on the live card.
        $staffIds = $day !== 'all' && (int) $day === 0 ? collect() : $event->persistentParticipantStaffIds();
        $presentIds = $presentAttendeeIds->merge($staffIds)->unique();

        $presentUsers = Participant::query()
            ->whereIn('id', $presentIds)
            ->with(['attendances' => function ($query) use ($event, $day, $session): void {
                $query->where('event_id', $event->id)
                    ->where(function ($query) use ($day, $session): void {
                        if ($day === 'all') {
                            $query->where('day', '>=', 1)->orWhere('day', -1);
                        } else {
                            $query->where(function ($query) use ($day, $session): void {
                                $query->where('day', $day);
                                if ($session !== null) {
                                    $query->where('session', $session);
                                }
                            })->orWhere('day', -1);
                        }
                    })
                    ->orderBy('day');
            }])
            ->orderBy('name')
            ->get();

        $absentUsers = Participant::query()
            ->whereIn('id', $eligibleIds->diff($presentAttendeeIds))
            ->orderBy('name')
            ->get();

        return [
            'presentUsers' => $presentUsers,
            'absentUsers' => $absentUsers,
            'totalExpected' => $eligibleIds->count() + $staffIds->count(),
        ];
    }
}
