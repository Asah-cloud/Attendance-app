<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_messages', function (Blueprint $table) {
            // Every message that already exists was sent the moment it was created.
            $table->string('status', 12)->default('sent')->after('mode');
            $table->timestamp('scheduled_at')->nullable()->after('status');
            $table->timestamp('sent_at')->nullable()->after('scheduled_at');
            // Who a draft or scheduled message will go to, worked out only when it is sent:
            // {"participants": [ids], "extras": [{"name", "email", "phone"}]}
            $table->json('draft_recipients')->nullable()->after('attachments');

            $table->index(['event_id', 'status']);
            $table->index(['status', 'scheduled_at']);
        });

        DB::table('custom_messages')->update(['sent_at' => DB::raw('created_at')]);

        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 80);
            $table->string('subject')->nullable();
            $table->text('email_body')->nullable();
            $table->text('sms_body')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');

        Schema::table('custom_messages', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'status']);
            $table->dropIndex(['status', 'scheduled_at']);
            $table->dropColumn(['status', 'scheduled_at', 'sent_at', 'draft_recipients']);
        });
    }
};
