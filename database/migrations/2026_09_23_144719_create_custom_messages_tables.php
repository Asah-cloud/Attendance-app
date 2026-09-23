<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('mode', 20)->default('smart');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'created_at']);
        });

        Schema::create('custom_message_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('channel', 10)->nullable();
            $table->string('status', 15)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['custom_message_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_message_recipients');
        Schema::dropIfExists('custom_messages');
    }
};
