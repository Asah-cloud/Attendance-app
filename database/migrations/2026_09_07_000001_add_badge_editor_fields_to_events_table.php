<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('badge_fields')->nullable();
            $table->string('badge_font', 30)->default('DejaVu Sans');
            $table->string('badge_name_format', 20)->default('full');
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn(['badge_fields', 'badge_font', 'badge_name_format']));
    }
};
