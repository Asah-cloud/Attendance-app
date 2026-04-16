<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();

            // the person attending
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // the specific event
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            // The Admin or marker who recorded this
            $table->foreignId('marked_by')->nullable();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
