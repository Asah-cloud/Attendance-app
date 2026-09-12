<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            // Free-text "room together" label (e.g. an area/branch/family name). The room
            // allocator prefers seating people who share this value in the same room.
            $table->string('room_group')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn('room_group');
        });
    }
};
