<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Lets attendees check themselves in on the shared check-in link with their phone number.
            $table->boolean('self_check_in_enabled')->default(false);
        });

        Schema::table('attendances', function (Blueprint $table) {
            // How the check-in happened, where known: 'self' for an attendee's own phone-number check-in.
            $table->string('method', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', fn (Blueprint $table) => $table->dropColumn('method'));
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('self_check_in_enabled'));
    }
};
