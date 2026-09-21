<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Participant;
use Illuminate\Support\Collection;

class AttendanceReportData
{
    /** @return array{presentUsers: Collection, absentUsers: Collection, totalExpected: int} */
    public function forPeriod(Event $event, int|string $day): array
    {
        $eligible = $day === 'all' || (int) $day === 0
            ? $event->confirmedParticipants()
            : $event->attendanceEligibleParticipants();
        $eligibleIds = $eligible->pluck('participants.id');

        $presentAttendeeIds = Attendance::query()
            ->where('event_id', $event->id)
            ->whereIn('participant_id', $eligibleIds)
            ->when($day === 'all', fn ($query) => $query->where('day', '>=', 1), fn ($query) => $query->where('day', $day))
            ->distinct()
            ->pluck('participant_id');

        // Numbered participant staff have a persistent staff check-in and are
        // included on every program day, just as they are on the live card.
        $staffIds = $day !== 'all' && (int) $day === 0 ? collect() : $event->persistentParticipantStaffIds();
        $presentIds = $presentAttendeeIds->merge($staffIds)->unique();

        $presentUsers = Participant::query()
            ->whereIn('id', $presentIds)
            ->with(['attendances' => function ($query) use ($event, $day): void {
                $query->where('event_id', $event->id)
                    ->where(function ($query) use ($day): void {
                        if ($day === 'all') {
                            $query->where('day', '>=', 1)->orWhere('day', -1);
                        } else {
                            $query->where('day', $day)->orWhere('day', -1);
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
