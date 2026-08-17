<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('linked_user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('member_id')->nullable();
            $table->string('category')->default('Member');
            $table->timestamps();
            $table->index(['company_id', 'email']);
            $table->index(['company_id', 'phone']);
        });

        Schema::create('event_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->foreignId('participant_id')->nullable()->constrained()->cascadeOnDelete();
        });
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('participant_id')->nullable()->constrained()->cascadeOnDelete();
        });

        $participantUserIds = DB::table('event_registrations')->pluck('user_id')
            ->merge(DB::table('attendances')->pluck('user_id'))
            ->unique()
            ->values();
        $publicOnlyIds = DB::table('event_registrations')
            ->whereIn('source', ['public', 'import', 'walk_in'])
            ->pluck('user_id')
            ->unique();

        DB::table('event_registrations')
            ->join('users', 'users.id', '=', 'event_registrations.user_id')
            ->where('users.role', 'regular')
            ->select(['event_registrations.event_id', 'event_registrations.user_id'])
            ->distinct()
            ->orderBy('event_registrations.event_id')
            ->get()
            ->each(fn ($assignment) => DB::table('event_staff')->insertOrIgnore([
                'event_id' => $assignment->event_id,
                'user_id' => $assignment->user_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

        DB::table('users')->whereIn('id', $participantUserIds)->orderBy('id')->get()->each(function ($user): void {
            $participantId = DB::table('participants')->insertGetId([
                'company_id' => $user->company_id,
                'linked_user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'member_id' => $user->member_id,
                'category' => $user->category ?: 'Member',
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]);
            DB::table('event_registrations')->where('user_id', $user->id)->update(['participant_id' => $participantId]);
            DB::table('attendances')->where('user_id', $user->id)->update(['participant_id' => $participantId]);
        });

        $missingRegistrations = DB::table('event_registrations')->whereNull('participant_id')->count();
        $missingAttendances = DB::table('attendances')->whereNull('participant_id')->count();
        if ($missingRegistrations || $missingAttendances) {
            throw new RuntimeException("Participant migration incomplete: {$missingRegistrations} registrations and {$missingAttendances} attendances are unmapped.");
        }

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'user_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->unique(['event_id', 'participant_id']);
        });
        $hasLegacyAttendanceUnique = collect(Schema::getIndexes('attendances'))
            ->contains(fn (array $index) => $index['name'] === 'attendances_event_user_day_unique');
        Schema::table('attendances', function (Blueprint $table) use ($hasLegacyAttendanceUnique) {
            if ($hasLegacyAttendanceUnique) {
                $table->dropUnique('attendances_event_user_day_unique');
            }
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->unique(['event_id', 'participant_id', 'day'], 'attendances_event_participant_day_unique');
        });

        DB::table('users')->where('role', 'member')->delete();
        DB::table('users')->whereIn('id', $publicOnlyIds)->whereNotIn('role', ['admin', 'manager'])->delete();
        DB::table('users')->where('role', 'regular')->update(['role' => 'usher']);
        DB::table('roles')->where('name', 'regular')->update(['name' => 'usher']);
    }

    public function down(): void
    {
        throw new RuntimeException('Restore the pre-separation database backup to reverse this data migration safely.');
    }
};
