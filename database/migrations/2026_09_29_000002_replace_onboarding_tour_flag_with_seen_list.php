<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces the single "has this manager seen the tour" flag with a list of
     * per-page tour keys they've seen, now that the tour covers every page
     * instead of just the dashboard.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('onboarding_tour_completed_at');
            $table->json('onboarding_tours_seen')->nullable()->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('onboarding_tours_seen');
            $table->timestamp('onboarding_tour_completed_at')->nullable()->after('must_change_password');
        });
    }
};
