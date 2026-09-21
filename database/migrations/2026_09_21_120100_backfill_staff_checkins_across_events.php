<?php

use App\Models\Attendance;
use App\Services\StaffCheckInService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Attendance::query()
            ->where('day', -1)
            ->whereHas('participant', fn ($query) => $query->where('is_support_staff', true))
            ->select('participant_id')
            ->distinct()
            ->pluck('participant_id')
            ->each(fn (int $id) => app(StaffCheckInService::class)->checkIn($id));

        // Day 1 scans of numbered staff from before the dedicated staff marker
        // was introduced also count as their initial badge check-in.
        Attendance::query()
            ->where('day', 1)
            ->whereHas('participant', fn ($query) => $query->where('is_support_staff', true))
            ->with('participant')
            ->get()
            ->filter(fn (Attendance $attendance) => $attendance->participant->isNumberedParticipantStaff())
            ->pluck('participant_id')
            ->unique()
            ->each(fn (int $id) => app(StaffCheckInService::class)->checkIn($id));
    }

    public function down(): void
    {
        // Historical check-ins cannot be distinguished from new scans, so keep them.
    }
};
