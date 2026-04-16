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
    Schema::table('attendances', function (Blueprint $table) {
        // We add the day column here so we can record WHICH day they attended
        $table->integer('day')->default(1)->after('user_id');
    });
}

public function down(): void
{
    Schema::table('attendances', function (Blueprint $table) {
        $table->dropColumn('day');
    });
}
};
