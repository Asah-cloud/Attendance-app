<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_collection_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meal_distribution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->integer('quantity_change');
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['event_id', 'occurred_at']);
            $table->index(['meal_distribution_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_collection_audits');
    }
};
