<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('total_portions');
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['event_id', 'is_active']);
        });

        Schema::create('meal_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_distribution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->boolean('was_overridden')->default(false);
            $table->text('override_reason')->nullable();
            $table->timestamp('collected_at');
            $table->timestamps();
            $table->unique(['meal_distribution_id', 'event_registration_id'], 'meal_registration_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_collections');
        Schema::dropIfExists('meal_distributions');
    }
};
