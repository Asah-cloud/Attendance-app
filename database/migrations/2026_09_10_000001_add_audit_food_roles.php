<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Role::findOrCreate('audit_head', 'web');
        Role::findOrCreate('audit_staff', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Schema::create('meal_station_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_station_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['meal_station_id', 'user_id']);
        });
        Schema::create('audit_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by')->constrained('users')->cascadeOnDelete();
            $table->string('scope');
            $table->unsignedBigInteger('meal_id')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('code_hash', 64)->unique();
            $table->text('reason');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_approvals');
        Schema::dropIfExists('meal_station_staff');
    }
};
