<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendee_pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('scope_type', 20);
            $table->string('plan_key')->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('band_from');
            $table->unsignedInteger('band_to')->nullable();
            $table->unsignedInteger('rate_minor');
            $table->timestamps();

            $table->index(['scope_type', 'plan_key', 'company_id']);
        });

        // Seed placeholder platform-default tiers (GHS, editable by a super-admin afterwards).
        $now = now();
        DB::table('attendee_pricing_tiers')->insert([
            ['scope_type' => 'platform', 'band_from' => 0, 'band_to' => 100, 'rate_minor' => 200, 'created_at' => $now, 'updated_at' => $now],
            ['scope_type' => 'platform', 'band_from' => 100, 'band_to' => 300, 'rate_minor' => 150, 'created_at' => $now, 'updated_at' => $now],
            ['scope_type' => 'platform', 'band_from' => 300, 'band_to' => 500, 'rate_minor' => 100, 'created_at' => $now, 'updated_at' => $now],
            ['scope_type' => 'platform', 'band_from' => 500, 'band_to' => 1000, 'rate_minor' => 75, 'created_at' => $now, 'updated_at' => $now],
            ['scope_type' => 'platform', 'band_from' => 1000, 'band_to' => null, 'rate_minor' => 50, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendee_pricing_tiers');
    }
};
