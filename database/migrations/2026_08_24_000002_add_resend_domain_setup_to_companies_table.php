<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('resend_domain_id')->nullable();
            $table->string('resend_domain_name')->nullable();
            $table->string('resend_domain_status')->nullable();
            $table->json('resend_domain_records')->nullable();
            $table->text('resend_setup_error')->nullable();
            $table->timestamp('resend_last_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'resend_domain_id',
                'resend_domain_name',
                'resend_domain_status',
                'resend_domain_records',
                'resend_setup_error',
                'resend_last_checked_at',
            ]);
        });
    }
};
