<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\ParticipantAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ParticipantMergeService
{
    /**
     * Merge $duplicate into $primary: $primary survives and keeps its own
     * details, gaining only whatever fields it was missing from $duplicate.
     * Every registration, attendance record, and audit log entry moves to
     * $primary; where $primary already has one for the same event (or
     * event+day, for attendance), the duplicate's is discarded rather than
     * violating the unique constraint that already protects that pairing.
     */
    public function merge(Participant $primary, Participant $duplicate, User $actor): void
    {
        DB::transaction(function () use ($primary, $duplicate, $actor): void {
            foreach (['name', 'email', 'phone', 'member_id', 'category', 'gender'] as $field) {
                if (blank($primary->{$field}) && filled($duplicate->{$field})) {
                    $primary->{$field} = $duplicate->{$field};
                }
            }

            if (blank($primary->linked_user_id) && filled($duplicate->linked_user_id)) {
                $linkedUserId = $duplicate->linked_user_id;
                $duplicate->linked_user_id = null;
                $duplicate->save();
                $primary->linked_user_id = $linkedUserId;
            }

            $primary->save();

            $duplicate->registrations()->get()->each(function (EventRegistration $registration) use ($primary): void {
                if ($primary->registrations()->where('event_id', $registration->event_id)->exists()) {
                    $registration->delete();
                } else {
                    $registration->update(['participant_id' => $primary->id]);
                }
            });

            $duplicate->attendances()->get()->each(function (Attendance $attendance) use ($primary): void {
                $conflict = $primary->attendances()
                    ->where('event_id', $attendance->event_id)
                    ->where('day', $attendance->day)
                    ->exists();

                if ($conflict) {
                    $attendance->delete();
                } else {
                    $attendance->update(['participant_id' => $primary->id]);
                }
            });

            $duplicate->auditLogs()->update(['participant_id' => $primary->id]);

            ParticipantAuditLog::create([
                'participant_id' => $primary->id,
                'user_id' => $actor->id,
                'changes' => [
                    'merged' => [
                        'old' => null,
                        'new' => "Merged with duplicate record #{$duplicate->id} ({$duplicate->name})",
                    ],
                ],
            ]);

            $duplicate->delete();
        });
    }
}
