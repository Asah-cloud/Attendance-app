<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_messages', function (Blueprint $table) {
            $table->dropColumn('body');
        });

        Schema::table('custom_messages', function (Blueprint $table) {
            $table->text('email_body')->nullable()->after('subject');
            $table->text('sms_body')->nullable()->after('email_body');
            $table->json('attachments')->nullable()->after('sms_body');
        });
    }

    public function down(): void
    {
        Schema::table('custom_messages', function (Blueprint $table) {
            $table->dropColumn(['email_body', 'sms_body', 'attachments']);
        });

        Schema::table('custom_messages', function (Blueprint $table) {
            $table->text('body')->after('subject');
        });
    }
};
