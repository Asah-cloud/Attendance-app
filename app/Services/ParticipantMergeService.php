<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\BadgePrint;
use App\Models\EventRegistration;
use App\Models\MealCollectionAudit;
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
                $kept = $primary->registrations()->where('event_id', $registration->event_id)->first();
                if ($kept) {
                    $this->foldInto($registration, $kept);
                } else {
                    $registration->update(['participant_id' => $primary->id]);
                }
            });

            $duplicate->attendances()->get()->each(function (Attendance $attendance) use ($primary): void {
                $conflict = $primary->attendances()
                    ->where('event_id', $attendance->event_id)
                    ->where('day', $attendance->day)
                    ->where('session', $attendance->session)
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

    /**
     * Two registrations for one event: keep $kept and move what hangs off $gone onto it
     * before $gone is deleted. Its printed QR code becomes an alias of $kept, so that
     * badge keeps scanning, and its food collections, badge prints and room carry over.
     */
    private function foldInto(EventRegistration $gone, EventRegistration $kept): void
    {
        $codes = DB::table('registration_code_aliases')->where('event_registration_id', $gone->id)->pluck('code')
            ->push($gone->registration_code)->filter()->unique();
        DB::table('registration_code_aliases')->where('event_registration_id', $gone->id)->delete();
        foreach ($codes as $code) {
            DB::table('registration_code_aliases')->insert(['event_id' => $kept->event_id, 'event_registration_id' => $kept->id,
                'code' => $code, 'created_at' => now(), 'updated_at' => now()]);
        }

        foreach ($gone->mealCollections()->get() as $collection) {
            $existing = $kept->mealCollections()->where('meal_distribution_id', $collection->meal_distribution_id)->first();
            if ($existing) {
                $existing->increment('quantity', $collection->quantity);
                $collection->delete();
            } else {
                $collection->update(['event_registration_id' => $kept->id, 'participant_id' => $kept->participant_id]);
            }
        }
        MealCollectionAudit::where('event_registration_id', $gone->id)->update(['event_registration_id' => $kept->id, 'participant_id' => $kept->participant_id]);
        BadgePrint::where('event_registration_id', $gone->id)->update(['event_registration_id' => $kept->id]);
        if (! $kept->roomAssignment()->exists()) {
            $gone->roomAssignment()->update(['event_registration_id' => $kept->id]);
        }

        $gone->delete();
    }
}
