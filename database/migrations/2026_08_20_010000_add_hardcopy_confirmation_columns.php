<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->text('confirmation_message')->nullable()->after('registration_terms_version');
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->timestamp('confirmation_sent_at')->nullable()->after('reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('confirmation_message');
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn('confirmation_sent_at');
        });
    }
};
