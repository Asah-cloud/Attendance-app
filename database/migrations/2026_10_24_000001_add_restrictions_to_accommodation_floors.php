<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accommodation_floors', function (Blueprint $table) {
            // Like a building's or room's: a room's own setting wins, then its floor's, then its building's.
            $table->string('gender_restriction', 30)->nullable();
            $table->string('category_restriction')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('accommodation_floors', fn (Blueprint $table) => $table->dropColumn(['gender_restriction', 'category_restriction']));
    }
};
