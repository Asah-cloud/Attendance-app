<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_station_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_distribution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meal_station_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('allocated_portions');
            $table->timestamps();

            $table->unique(['meal_distribution_id', 'meal_station_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_station_allocations');
    }
};
