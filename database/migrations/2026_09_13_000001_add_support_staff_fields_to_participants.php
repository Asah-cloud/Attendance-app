<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('department')->nullable()->after('category');
            $table->boolean('is_support_staff')->default(false)->after('department');
            $table->string('staff_code')->nullable()->unique()->after('is_support_staff');
            $table->string('staff_qr_token', 64)->nullable()->unique()->after('staff_code');
            $table->index(['company_id', 'is_support_staff']);
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'is_support_staff']);
            $table->dropUnique(['staff_code']);
            $table->dropUnique(['staff_qr_token']);
            $table->dropColumn(['department', 'is_support_staff', 'staff_code', 'staff_qr_token']);
        });
    }
};
