<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->unsignedInteger('price_minor');
            $table->unsignedInteger('event_limit');
            $table->unsignedInteger('participant_limit');
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->boolean('featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('plans')->insert([
            [
                'key' => 'starter',
                'name' => 'Starter',
                'price_minor' => 9900,
                'event_limit' => 3,
                'participant_limit' => 500,
                'description' => 'For smaller organisations running focused events.',
                'features' => json_encode([
                    'Up to 3 active events',
                    'Up to 500 participants per event',
                    'QR and manual check-in',
                    'Custom event registration forms',
                    'Daily attendance & summary reports',
                    'Excel, CSV and PDF exports',
                    'Excel participant import',
                    'Hard-copy confirmation + automatic duplicate detection',
                ]),
                'featured' => false,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'business',
                'name' => 'Business',
                'price_minor' => 29900,
                'event_limit' => 15,
                'participant_limit' => 5000,
                'description' => 'For growing teams that need more control and visibility.',
                'features' => json_encode([
                    'Up to 15 active events',
                    'Up to 5,000 participants per event',
                    'Everything in Starter, plus:',
                    'Food / meal distribution tracking',
                    'Printable A5/A6 attendee badges',
                    'Multiple manager accounts',
                    'Company & event branding (logo, flyer)',
                    'Custom SMS sender ID & email sending domain',
                    'Priority support',
                ]),
                'featured' => true,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'enterprise',
                'name' => 'Enterprise',
                'price_minor' => 79900,
                'event_limit' => 50,
                'participant_limit' => 25000,
                'description' => 'For large organisations with advanced operating needs.',
                'features' => json_encode([
                    'Up to 50 active events',
                    'Up to 25,000 participants per event',
                    'Everything in Business, plus:',
                    'Multi-location support',
                    'Dedicated delivery reliability monitoring',
                    'Tailored onboarding',
                    'Priority support',
                ]),
                'featured' => false,
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
