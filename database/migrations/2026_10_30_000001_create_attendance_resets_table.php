<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each "Reset attendance", with the check-ins it removed, so it can be undone.
        Schema::create('attendance_resets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Null for every day.
            $table->unsignedSmallInteger('day')->nullable();
            $table->unsignedInteger('removed_count');
            $table->json('rows');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->foreignId('undone_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_resets');
    }
};
