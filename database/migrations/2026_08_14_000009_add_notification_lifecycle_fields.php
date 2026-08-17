<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->index();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->index();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->timestamp('subscription_expiry_warning_sent_at')->nullable();
            $table->timestamp('subscription_expired_notice_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', fn (Blueprint $table) => $table->dropColumn('reminder_sent_at'));
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('cancelled_at'));
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['subscription_expiry_warning_sent_at', 'subscription_expired_notice_sent_at']);
        });
    }
};
