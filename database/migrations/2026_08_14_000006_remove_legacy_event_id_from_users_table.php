<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $assignmentsWithoutRegistration = DB::table('users')
            ->leftJoin('event_registrations', function ($join): void {
                $join->on('event_registrations.event_id', '=', 'users.event_id')
                    ->on('event_registrations.user_id', '=', 'users.id');
            })
            ->whereNotNull('users.event_id')
            ->whereNull('event_registrations.id')
            ->count();

        $attendanceWithoutRegistration = DB::table('attendances')
            ->leftJoin('event_registrations', function ($join): void {
                $join->on('event_registrations.event_id', '=', 'attendances.event_id')
                    ->on('event_registrations.user_id', '=', 'attendances.user_id');
            })
            ->whereNull('event_registrations.id')
            ->count();

        if ($assignmentsWithoutRegistration > 0 || $attendanceWithoutRegistration > 0) {
            throw new RuntimeException(
                "Cannot remove users.event_id: {$assignmentsWithoutRegistration} assignments and "
                ."{$attendanceWithoutRegistration} attendance records are missing registrations."
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropColumn('event_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
        });

        DB::table('event_registrations')
            ->where('source', 'legacy_event_id')
            ->orderBy('id')
            ->get(['user_id', 'event_id'])
            ->each(function ($registration): void {
                DB::table('users')
                    ->where('id', $registration->user_id)
                    ->whereNull('event_id')
                    ->update(['event_id' => $registration->event_id]);
            });
    }
};
