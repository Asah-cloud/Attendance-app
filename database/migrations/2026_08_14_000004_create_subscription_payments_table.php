<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('subscription_auto_renews')->default(true);
            $table->timestamp('subscription_cancelled_at')->nullable();
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('plan_key');
            $table->string('type', 20);
            $table->unsignedInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('payment_reference')->unique();
            $table->string('status', 20)->default('paid');
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index(['company_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['subscription_auto_renews', 'subscription_cancelled_at']);
        });
    }
};
