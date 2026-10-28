<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** One row each time a person's badge is printed (or marked as printed), with what was on it. */
    public function up(): void
    {
        Schema::create('badge_prints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // The person's details as printed on the badge, keyed by field label.
            $table->json('details');
            // Marked as printed by hand (e.g. printed before prints were recorded), not from a PDF.
            $table->boolean('marked')->default(false);
            $table->timestamps();
            $table->index(['event_id', 'event_registration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_prints');
    }
};
