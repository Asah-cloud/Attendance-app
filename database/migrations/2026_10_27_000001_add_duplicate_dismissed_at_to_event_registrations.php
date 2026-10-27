<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            // Set when a manager says people sharing this name are different people, so the
            // possible-duplicates box stops showing them until someone new with the name registers.
            $table->timestamp('duplicate_dismissed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', fn (Blueprint $table) => $table->dropColumn('duplicate_dismissed_at'));
    }
};
