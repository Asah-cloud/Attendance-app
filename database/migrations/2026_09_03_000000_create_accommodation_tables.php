<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('accommodation_enabled')->default(false)->after('arrival_date');
            $table->boolean('accommodation_published')->default(false)->after('accommodation_enabled');
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->boolean('accommodation_required')->default(false);
            $table->boolean('accessibility_required')->default(false);
            $table->text('accommodation_notes')->nullable();
        });

        Schema::create('accommodation_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('address')->nullable();
            $table->text('check_in_instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['event_id', 'name']);
        });

        Schema::create('accommodation_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accommodation_site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('gender_restriction', 30)->nullable();
            $table->string('category_restriction')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['accommodation_site_id', 'name']);
        });

        Schema::create('accommodation_floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accommodation_block_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_accessible')->default(false);
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['accommodation_block_id', 'name']);
        });

        Schema::create('accommodation_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accommodation_floor_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('capacity');
            $table->string('gender_restriction', 30)->nullable();
            $table->string('category_restriction')->nullable();
            $table->boolean('is_accessible')->default(false);
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('priority')->default(100);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['accommodation_floor_id', 'name']);
        });

        Schema::create('room_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accommodation_room_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('assigned');
            $table->string('method', 20)->default('automatic');
            $table->boolean('is_locked')->default(false);
            $table->text('allocation_reason')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamps();
            $table->unique('event_registration_id');
            $table->index(['accommodation_room_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_assignments');
        Schema::dropIfExists('accommodation_rooms');
        Schema::dropIfExists('accommodation_floors');
        Schema::dropIfExists('accommodation_blocks');
        Schema::dropIfExists('accommodation_sites');
        Schema::table('event_registrations', fn (Blueprint $table) => $table->dropColumn(['accommodation_required', 'accessibility_required', 'accommodation_notes']));
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn(['accommodation_enabled', 'accommodation_published']));
    }
};
