<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('badge_size', 2)->default('A6');
            $table->string('badge_design', 20)->default('default');
            $table->json('badge_category_colors')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn(['badge_size', 'badge_design', 'badge_category_colors']));
    }
};
