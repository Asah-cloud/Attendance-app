<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments attendees make on a custom form or the registration form, paid through Paystack
 * into the organisation's own sub-account (their Mobile Money or bank account).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('payout_subaccount_code')->nullable();
            $table->string('payout_business_name')->nullable();
            $table->string('payout_type')->nullable();
            $table->string('payout_bank_code')->nullable();
            $table->string('payout_bank_name')->nullable();
            $table->string('payout_account_number')->nullable();
            $table->timestamp('payout_updated_at')->nullable();
        });

        foreach (['forms' => 'payment_', 'events' => 'registration_payment_'] as $tableName => $prefix) {
            Schema::table($tableName, function (Blueprint $table) use ($prefix) {
                $table->boolean($prefix.'enabled')->default(false);
                $table->string($prefix.'label')->nullable();
                $table->unsignedInteger($prefix.'amount_minor')->nullable();
                $table->boolean($prefix.'payer_sets_amount')->default(false);
                $table->unsignedInteger($prefix.'minimum_minor')->nullable();
            });
        }

        // Null when nothing was asked for; otherwise unpaid until Paystack confirms.
        foreach (['form_responses', 'event_registrations'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('payment_status')->nullable()->index();
                $table->unsignedInteger('amount_paid_minor')->nullable();
            });
        }

        Schema::create('attendee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->morphs('payable');
            $table->string('reference')->unique();
            $table->unsignedInteger('amount_minor');
            $table->unsignedInteger('platform_fee_minor')->default(0);
            $table->string('currency', 3);
            $table->string('status')->default('pending');
            $table->string('payer_email');
            $table->string('channel')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendee_payments');
        foreach (['form_responses', 'event_registrations'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn(['payment_status', 'amount_paid_minor']));
        }
        foreach (['forms' => 'payment_', 'events' => 'registration_payment_'] as $tableName => $prefix) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn(array_map(fn ($column) => $prefix.$column, ['enabled', 'label', 'amount_minor', 'payer_sets_amount', 'minimum_minor'])));
        }
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn(['payout_subaccount_code', 'payout_business_name', 'payout_type', 'payout_bank_code', 'payout_bank_name', 'payout_account_number', 'payout_updated_at']));
    }
};
