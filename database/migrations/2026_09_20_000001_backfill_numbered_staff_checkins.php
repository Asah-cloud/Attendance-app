<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('attendances as attendance')
            ->join('participants as participant', 'participant.id', '=', 'attendance.participant_id')
            ->join('event_registrations as registration', function ($join): void {
                $join->on('registration.event_id', '=', 'attendance.event_id')
                    ->on('registration.participant_id', '=', 'attendance.participant_id');
            })
            ->where('attendance.day', 1)
            ->where('participant.is_support_staff', true)
            ->where('registration.status', 'confirmed')
            ->whereRaw('LOWER(TRIM(participant.name)) LIKE ?', ['participant %'])
            ->select([
                'attendance.event_id',
                'attendance.participant_id',
                'attendance.status',
                'attendance.marked_by',
                'attendance.created_at',
                'attendance.updated_at',
                'participant.name',
            ])
            ->orderBy('attendance.id')
            ->chunk(500, function ($records): void {
                $checkIns = $records
                    ->filter(fn ($record) => preg_match('/^participant\s+\d+$/i', trim($record->name)) === 1)
                    ->map(fn ($record) => [
                        'event_id' => $record->event_id,
                        'participant_id' => $record->participant_id,
                        'day' => -1,
                        'status' => $record->status,
                        'marked_by' => $record->marked_by,
                        'created_at' => $record->created_at,
                        'updated_at' => $record->updated_at,
                    ])->all();

                if ($checkIns !== []) {
                    DB::table('attendances')->insertOrIgnore($checkIns);
                }
            });
    }

    public function down(): void
    {
        // The backfilled rows are valid staff check-ins and intentionally retained.
    }
};
