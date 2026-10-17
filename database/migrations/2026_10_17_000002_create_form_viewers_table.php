<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // People a manager has let see (and export) one form's responses, and nothing else.
        Schema::create('form_viewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['form_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_viewers');
    }
};
