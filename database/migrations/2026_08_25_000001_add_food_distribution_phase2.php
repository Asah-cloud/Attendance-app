<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->text('dietary_notes')->nullable();
        });

        Schema::create('meal_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_distribution_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->unsignedInteger('portions_allowed');
            $table->timestamps();

            $table->unique(['meal_distribution_id', 'category']);
        });

        Schema::create('meal_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['event_id', 'name']);
        });

        Schema::table('meal_collections', function (Blueprint $table) {
            $table->foreignId('meal_station_id')->nullable()->after('meal_distribution_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meal_collections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('meal_station_id');
        });

        Schema::dropIfExists('meal_stations');
        Schema::dropIfExists('meal_entitlements');

        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn('dietary_notes');
        });
    }
};
