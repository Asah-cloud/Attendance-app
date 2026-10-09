<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->unsignedInteger('session')->default(1)->after('day');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique('attendances_event_participant_day_unique');
            $table->unique(['event_id', 'participant_id', 'day', 'session'], 'attendances_event_participant_day_session_unique');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique('attendances_event_participant_day_session_unique');
            $table->unique(['event_id', 'participant_id', 'day'], 'attendances_event_participant_day_unique');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('session');
        });
    }
};
