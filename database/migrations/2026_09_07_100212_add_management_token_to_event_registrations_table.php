<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->string('management_token')->nullable()->unique()->after('registration_code');
        });

        DB::table('event_registrations')->whereNull('management_token')->orderBy('id')->chunkById(500, function ($registrations) {
            foreach ($registrations as $registration) {
                DB::table('event_registrations')->where('id', $registration->id)->update(['management_token' => Str::random(40)]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn('management_token');
        });
    }
};
