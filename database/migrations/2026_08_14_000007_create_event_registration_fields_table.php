<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->text('registration_terms')->nullable();
            $table->string('registration_terms_version')->default('v1');
        });

        Schema::create('event_registration_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('field_key');
            $table->string('label');
            $table->string('field_type', 20);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_required')->default(false);
            $table->json('options')->nullable();
            $table->json('validation_rules')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['event_id', 'field_key']);
            $table->index(['event_id', 'is_active', 'display_order']);
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->json('custom_answers')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->string('terms_version')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn(['custom_answers', 'consented_at', 'terms_version']);
        });

        Schema::dropIfExists('event_registration_fields');

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['registration_terms', 'registration_terms_version']);
        });
    }
};
