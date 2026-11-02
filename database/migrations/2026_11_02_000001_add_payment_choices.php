<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Priced choices on a paid form ("Adult: 200, Student: 100"): people pick one and pay its price. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', fn (Blueprint $table) => $table->json('payment_options')->nullable());
        Schema::table('events', fn (Blueprint $table) => $table->json('registration_payment_options')->nullable());
        foreach (['form_responses', 'event_registrations', 'attendee_payments'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->string('payment_option')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['form_responses', 'event_registrations', 'attendee_payments'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('payment_option'));
        }
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('registration_payment_options'));
        Schema::table('forms', fn (Blueprint $table) => $table->dropColumn('payment_options'));
    }
};
