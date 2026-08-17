<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('attendances')
            ->select('event_id', 'user_id', 'day', DB::raw('MIN(id) as keep_id'))
            ->groupBy('event_id', 'user_id', 'day')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function ($duplicate): void {
                DB::table('attendances')
                    ->where('event_id', $duplicate->event_id)
                    ->where('user_id', $duplicate->user_id)
                    ->where('day', $duplicate->day)
                    ->where('id', '!=', $duplicate->keep_id)
                    ->delete();
            });

        Schema::table('attendances', function (Blueprint $table) {
            $table->unique(['event_id', 'user_id', 'day'], 'attendances_event_user_day_unique');
            $table->index(['event_id', 'day'], 'attendances_event_day_index');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->index(['company_id', 'event_date'], 'events_company_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique('attendances_event_user_day_unique');
            $table->dropIndex('attendances_event_day_index');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_company_date_index');
        });
    }
};
