<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            // Chosen by a manager when adding or editing someone. Reports use it, and fall back
            // to the country of the phone number when it's blank.
            $table->string('country', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('participants', fn (Blueprint $table) => $table->dropColumn('country'));
    }
};
