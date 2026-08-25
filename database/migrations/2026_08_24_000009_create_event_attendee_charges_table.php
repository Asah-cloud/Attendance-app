<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_attendee_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending_payment');
            $table->unsignedInteger('registered_count');
            $table->json('tier_breakdown');
            $table->unsignedInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('payment_reference')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('finalized_at');
            $table->unsignedInteger('checked_in_count')->nullable();
            $table->json('refund_breakdown')->nullable();
            $table->unsignedInteger('refund_amount_minor')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_attendee_charges');
    }
};
