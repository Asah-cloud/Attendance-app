<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_attendee_charges', function (Blueprint $table) {
            $table->unsignedInteger('discount_minor')->default(0)->after('features_amount_minor');
            $table->string('discount_reason')->nullable()->after('discount_minor');
            $table->string('invoice_number')->nullable()->unique()->after('discount_reason');
            $table->foreignId('reviewed_by')->nullable()->after('invoice_number')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->timestamp('invoice_emailed_at')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('event_attendee_charges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['discount_minor', 'discount_reason', 'invoice_number', 'reviewed_at', 'invoice_emailed_at']);
        });
    }
};
