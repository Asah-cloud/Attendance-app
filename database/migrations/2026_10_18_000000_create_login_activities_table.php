<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every sign-in and failed sign-in attempt, for the platform admins' sign-in bell.
        Schema::create('login_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('roles')->nullable();
            $table->boolean('successful');
            // A failed attempt that completed a run of repeated failures for one email.
            $table->boolean('alert')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->index();
        });

        Schema::table('users', function (Blueprint $table) {
            // When a platform admin last opened the sign-in bell (unread count = newer entries).
            $table->timestamp('login_activity_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('login_activity_seen_at'));
        Schema::dropIfExists('login_activities');
    }
};
