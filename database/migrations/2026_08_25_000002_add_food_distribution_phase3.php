<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_distributions', function (Blueprint $table) {
            $table->unsignedInteger('low_stock_threshold')->nullable();
            $table->timestamp('low_stock_notified_at')->nullable();
        });

        Schema::create('meal_waste_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_distribution_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('reason');
            $table->foreignId('logged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['meal_distribution_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_waste_logs');

        Schema::table('meal_distributions', function (Blueprint $table) {
            $table->dropColumn(['low_stock_threshold', 'low_stock_notified_at']);
        });
    }
};
