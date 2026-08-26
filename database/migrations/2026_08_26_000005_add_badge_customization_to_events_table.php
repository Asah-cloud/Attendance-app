<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('badge_layout', 20)->default('standard');
            $table->string('badge_image_path')->nullable();
            $table->string('badge_primary_color', 7)->default('#0F766E');
            $table->string('badge_accent_color', 7)->default('#0F172A');
            $table->unsignedTinyInteger('badge_image_position_x')->default(50);
            $table->unsignedTinyInteger('badge_image_position_y')->default(50);
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn([
            'badge_layout',
            'badge_image_path',
            'badge_primary_color',
            'badge_accent_color',
            'badge_image_position_x',
            'badge_image_position_y',
        ]));
    }
};
