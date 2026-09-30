<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_attendee_charges', function (Blueprint $table) {
            $table->foreignId('paid_manually_by')->nullable()->after('payment_reference')->constrained('users')->nullOnDelete();
            $table->string('manual_payment_note')->nullable()->after('paid_manually_by');
        });
    }

    public function down(): void
    {
        Schema::table('event_attendee_charges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_manually_by');
            $table->dropColumn('manual_payment_note');
        });
    }
};
