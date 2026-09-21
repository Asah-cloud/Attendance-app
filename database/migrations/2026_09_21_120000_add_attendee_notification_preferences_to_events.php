<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->boolean('automatic_attendee_email')->default(false);
            $table->boolean('automatic_attendee_sms')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['automatic_attendee_email', 'automatic_attendee_sms']);
        });
    }
};
