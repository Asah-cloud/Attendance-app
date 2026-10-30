<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Staff added to sign in with an emailed code instead of a password (see SignInCode).
            $table->boolean('signs_in_with_code')->default(false);
            // The current code, hashed; one at a time, cleared once used.
            $table->string('sign_in_code')->nullable();
            $table->timestamp('sign_in_code_expires_at')->nullable();
            $table->unsignedTinyInteger('sign_in_code_attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['signs_in_with_code', 'sign_in_code', 'sign_in_code_expires_at', 'sign_in_code_attempts']);
        });
    }
};
