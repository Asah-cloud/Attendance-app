<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('plan_key')->nullable();
            $table->unsignedInteger('plan_price_minor')->nullable();
            $table->string('billing_currency', 3)->nullable();
            $table->string('payment_reference')->nullable()->unique();
            $table->timestamp('subscription_started_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['payment_reference']);
            $table->dropColumn([
                'plan_key',
                'plan_price_minor',
                'billing_currency',
                'payment_reference',
                'subscription_started_at',
            ]);
        });
    }
};
