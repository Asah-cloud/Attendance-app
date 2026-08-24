<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->timestamp('resend_setup_started_at')->nullable();
            $table->timestamp('resend_first_reminder_sent_at')->nullable();
            $table->timestamp('resend_delayed_notice_sent_at')->nullable();
            $table->timestamp('resend_failure_notice_sent_at')->nullable();
            $table->timestamp('resend_verified_notice_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'resend_setup_started_at',
                'resend_first_reminder_sent_at',
                'resend_delayed_notice_sent_at',
                'resend_failure_notice_sent_at',
                'resend_verified_notice_sent_at',
            ]);
        });
    }
};
