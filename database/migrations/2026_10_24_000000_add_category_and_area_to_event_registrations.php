<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Category and area (room_group) belong to a person's registration for one event, not to the
 * person: importing the same minister into a new event as "Pastor" in "Takoradi" must not
 * change what an earlier event recorded for them. Existing registrations start with the
 * values their person has today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->string('category')->nullable()->after('status');
            $table->string('room_group')->nullable()->after('category');
        });

        DB::table('event_registrations')->update([
            'category' => DB::raw('(SELECT participants.category FROM participants WHERE participants.id = event_registrations.participant_id)'),
            'room_group' => DB::raw('(SELECT participants.room_group FROM participants WHERE participants.id = event_registrations.participant_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn(['category', 'room_group']);
        });
    }
};
