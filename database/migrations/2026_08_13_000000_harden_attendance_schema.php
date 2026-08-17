<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNull('role')->update(['role' => 'regular']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('regular')->change();
        });

    }

    public function down(): void
    {
        // The safer default and duplicate cleanup are intentionally irreversible.
    }
};
