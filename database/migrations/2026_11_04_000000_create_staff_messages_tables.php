<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages from admins and managers to their ushers, meal-station and audit staff. Each recipient
 * gets a row so the sender can see who has read it. Staff can only read; there are no replies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('audience', 20);
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('urgent')->default(false);
            $table->timestamps();
            $table->index(['company_id', 'created_at']);
        });

        Schema::create('staff_message_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['staff_message_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_message_recipients');
        Schema::dropIfExists('staff_messages');
    }
};
