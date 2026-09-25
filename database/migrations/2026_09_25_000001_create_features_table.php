<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('tier', 20)->default('advanced');
            $table->unsignedInteger('cost_minor')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('features')->insert([
            [
                'key' => 'custom_messages',
                'name' => 'Custom Messages (Email & SMS)',
                'tier' => 'advanced',
                'cost_minor' => 5000,
                'description' => 'Import recipients, write your own subject/body, and send bulk email and SMS with smart Ghana/foreign routing.',
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'badge_studio',
                'name' => 'Badge / ID Studio',
                'tier' => 'advanced',
                'cost_minor' => 5000,
                'description' => 'Design and print attendee and staff badges with QR codes.',
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'advanced_reports',
                'name' => 'Advanced Reports & Exports',
                'tier' => 'advanced',
                'cost_minor' => 3000,
                'description' => 'Per-area breakdowns and Excel, CSV and PDF exports of attendance and summary reports.',
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'rooms',
                'name' => 'Room & Accommodation Management',
                'tier' => 'advanced',
                'cost_minor' => 4000,
                'description' => 'Sites, blocks, floors and room allocation with self-select and check-in/out.',
                'is_active' => true,
                'sort_order' => 4,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'food',
                'name' => 'Food / Meal Distribution',
                'tier' => 'advanced',
                'cost_minor' => 4000,
                'description' => 'Meal stations, QR-based issuing, waste logging and meal reports.',
                'is_active' => true,
                'sort_order' => 5,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};
