<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom form responses can be added to the event's attendee list: the form remembers
 * which question holds the name, phone, etc. and whether new responses are added
 * automatically; each response remembers the registration it was added as.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->json('attendee_mapping')->nullable();
            $table->boolean('auto_add_attendees')->default(false);
        });

        Schema::table('form_responses', function (Blueprint $table) {
            $table->foreignId('event_registration_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('form_responses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_registration_id');
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn(['attendee_mapping', 'auto_add_attendees']);
        });
    }
};
