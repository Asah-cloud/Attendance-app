<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A record of each completed participant list import, so a manager can undo it. */
    public function up(): void
    {
        Schema::create('participant_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_name');
            $table->unsignedInteger('added_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            // Registrations this import created in the event, and people it created in the company.
            $table->json('registration_ids');
            $table->json('participant_ids');
            $table->timestamp('undone_at')->nullable();
            $table->foreignId('undone_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_imports');
    }
};
