<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('email_from_name')->nullable();
            $table->string('email_from_address')->nullable();
            $table->string('email_sender_status')->default('unconfigured');
            $table->string('sms_sender_id', 11)->nullable();
            $table->string('sms_sender_status')->default('unconfigured');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'email_from_name',
                'email_from_address',
                'email_sender_status',
                'sms_sender_id',
                'sms_sender_status',
            ]);
        });
    }
};
