<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_attendee_charges', function (Blueprint $table) {
            $table->unsignedInteger('features_amount_minor')->default(0)->after('amount_minor');
            $table->json('feature_breakdown')->nullable()->after('features_amount_minor');
        });
    }

    public function down(): void
    {
        Schema::table('event_attendee_charges', function (Blueprint $table) {
            $table->dropColumn(['features_amount_minor', 'feature_breakdown']);
        });
    }
};
