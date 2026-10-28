<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** How many badges a large print holds, and when its badges were first downloaded (and so recorded as printed). */
    public function up(): void
    {
        Schema::table('badge_exports', function (Blueprint $table) {
            $table->unsignedInteger('badge_count')->default(0)->after('completed_batches');
            $table->timestamp('recorded_at')->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('badge_exports', function (Blueprint $table) {
            $table->dropColumn(['badge_count', 'recorded_at']);
        });
    }
};
