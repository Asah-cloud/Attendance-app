<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendee_pricing_tiers', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('company_id')->constrained()->cascadeOnDelete();
            $table->index(['scope_type', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::table('attendee_pricing_tiers', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropIndex(['scope_type', 'event_id']);
            $table->dropColumn('event_id');
        });
    }
};
