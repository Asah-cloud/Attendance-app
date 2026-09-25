<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Events finalized before advanced features existed were never offered a
     * choice to buy them, so they keep full access rather than being locked
     * out overnight. Only charges created after this migration (i.e. under
     * the new feature system) are subject to real per-feature gating.
     */
    public function up(): void
    {
        Schema::table('event_attendee_charges', function (Blueprint $table) {
            $table->boolean('grandfathered')->default(false)->after('feature_breakdown');
        });

        DB::table('event_attendee_charges')->update(['grandfathered' => true]);
    }

    public function down(): void
    {
        Schema::table('event_attendee_charges', function (Blueprint $table) {
            $table->dropColumn('grandfathered');
        });
    }
};
