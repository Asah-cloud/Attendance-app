<?php

namespace App\Console\Commands;

use App\Models\EventRegistration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class MigrateParticipantRegistrations extends Command
{
    protected $signature = 'participants:migrate-registrations
        {--dry-run : Audit and report without changing registrations}
        {--rollback : Remove only registrations created by this command}
        {--report= : Write the JSON report to a specific path}';

    protected $description = 'Audit and backfill event registrations from legacy assignments and attendance';

    public function handle(): int
    {
        if (! Schema::hasColumn('users', 'event_id')) {
            $this->info('The legacy users.event_id column has already been removed; Phase 3 is complete.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run') && $this->option('rollback')) {
            $this->error('The --dry-run and --rollback options cannot be combined.');

            return self::INVALID;
        }

        if ($this->option('rollback')) {
            return $this->rollback();
        }

        $report = [
            'mode' => $this->option('dry-run') ? 'dry-run' : 'execute',
            'started_at' => now()->toIso8601String(),
            'legacy_assignments_scanned' => 0,
            'attendance_pairs_scanned' => 0,
            'registrations_created_from_assignments' => 0,
            'registrations_created_from_attendance' => 0,
            'registrations_already_present' => 0,
            'attendance_event_conflicts' => [],
            'invalid_company_relationships' => [],
            'registration_status_conflicts' => [],
        ];

        $operation = function () use (&$report): void {
            $planned = [];

            DB::table('users')
                ->join('events', 'events.id', '=', 'users.event_id')
                ->whereNotNull('users.event_id')
                ->select([
                    'users.id as user_id',
                    'users.company_id as user_company_id',
                    'events.id as event_id',
                    'events.company_id as event_company_id',
                ])
                ->orderBy('users.id')
                ->each(function ($row) use (&$report, &$planned): void {
                    $report['legacy_assignments_scanned']++;

                    if ($this->companyMismatch($row->user_company_id, $row->event_company_id)) {
                        $report['invalid_company_relationships'][] = [
                            'context' => 'legacy_assignment',
                            'user_id' => $row->user_id,
                            'event_id' => $row->event_id,
                            'user_company_id' => $row->user_company_id,
                            'event_company_id' => $row->event_company_id,
                        ];

                        return;
                    }

                    $this->register(
                        (int) $row->event_id,
                        (int) $row->user_id,
                        'legacy_event_id',
                        'registrations_created_from_assignments',
                        $report,
                        $planned,
                    );
                });

            DB::table('attendances')
                ->join('users', 'users.id', '=', 'attendances.user_id')
                ->join('events', 'events.id', '=', 'attendances.event_id')
                ->select([
                    'attendances.event_id',
                    'attendances.user_id',
                    'users.event_id as legacy_event_id',
                    'users.company_id as user_company_id',
                    'events.company_id as event_company_id',
                ])
                ->distinct()
                ->orderBy('attendances.user_id')
                ->each(function ($row) use (&$report, &$planned): void {
                    $report['attendance_pairs_scanned']++;

                    if ($this->companyMismatch($row->user_company_id, $row->event_company_id)) {
                        $report['invalid_company_relationships'][] = [
                            'context' => 'attendance',
                            'user_id' => $row->user_id,
                            'event_id' => $row->event_id,
                            'user_company_id' => $row->user_company_id,
                            'event_company_id' => $row->event_company_id,
                        ];

                        return;
                    }

                    if ($row->legacy_event_id !== null && (int) $row->legacy_event_id !== (int) $row->event_id) {
                        $report['attendance_event_conflicts'][] = [
                            'user_id' => $row->user_id,
                            'assigned_event_id' => $row->legacy_event_id,
                            'attendance_event_id' => $row->event_id,
                        ];
                    }

                    $this->register(
                        (int) $row->event_id,
                        (int) $row->user_id,
                        'legacy_attendance',
                        'registrations_created_from_attendance',
                        $report,
                        $planned,
                    );
                });
        };

        if ($this->option('dry-run')) {
            $operation();
        } else {
            DB::transaction($operation);
        }

        $report['unregistered_attendance_pairs_after_run'] = $this->option('dry-run')
            ? null
            : $this->unregisteredAttendancePairs();
        $report['finished_at'] = now()->toIso8601String();
        $path = $this->writeReport($report);
        $this->displayReport($report, $path);

        return count($report['invalid_company_relationships']) > 0
            || count($report['registration_status_conflicts']) > 0
            || ($report['unregistered_attendance_pairs_after_run'] ?? 0) > 0
                ? self::FAILURE
                : self::SUCCESS;
    }

    private function register(
        int $eventId,
        int $userId,
        string $source,
        string $createdCounter,
        array &$report,
        array &$planned,
    ): void {
        $key = $eventId.':'.$userId;

        if (isset($planned[$key])) {
            $report['registrations_already_present']++;

            return;
        }

        $existing = EventRegistration::query()
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            $planned[$key] = true;
            $report['registrations_already_present']++;

            if ($existing->status !== EventRegistration::STATUS_CONFIRMED) {
                $report['registration_status_conflicts'][] = [
                    'registration_id' => $existing->id,
                    'user_id' => $userId,
                    'event_id' => $eventId,
                    'status' => $existing->status,
                ];
            }

            return;
        }

        $planned[$key] = true;
        $report[$createdCounter]++;

        if (! $this->option('dry-run')) {
            EventRegistration::create([
                'event_id' => $eventId,
                'user_id' => $userId,
                'status' => EventRegistration::STATUS_CONFIRMED,
                'approved_at' => now(),
                'source' => $source,
            ]);
        }
    }

    private function companyMismatch(mixed $userCompanyId, mixed $eventCompanyId): bool
    {
        return $userCompanyId !== null
            && $eventCompanyId !== null
            && (int) $userCompanyId !== (int) $eventCompanyId;
    }

    private function unregisteredAttendancePairs(): int
    {
        return DB::table('attendances')
            ->leftJoin('event_registrations', function ($join): void {
                $join->on('event_registrations.event_id', '=', 'attendances.event_id')
                    ->on('event_registrations.user_id', '=', 'attendances.user_id');
            })
            ->whereNull('event_registrations.id')
            ->select(['attendances.event_id', 'attendances.user_id'])
            ->distinct()
            ->get()
            ->count();
    }

    private function rollback(): int
    {
        $deleted = EventRegistration::query()
            ->whereIn('source', ['legacy_event_id', 'legacy_attendance'])
            ->delete();
        $report = [
            'mode' => 'rollback',
            'finished_at' => now()->toIso8601String(),
            'registrations_deleted' => $deleted,
        ];
        $path = $this->writeReport($report);
        $this->info("Removed {$deleted} Phase 3 registrations.");
        $this->line("Report: {$path}");

        return self::SUCCESS;
    }

    private function writeReport(array $report): string
    {
        $path = $this->option('report') ?: storage_path(
            'app/migration-reports/participant-registrations-'.now()->format('Ymd_His').'.json'
        );
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $path;
    }

    private function displayReport(array $report, string $path): void
    {
        $this->table(['Metric', 'Count'], [
            ['Legacy assignments scanned', $report['legacy_assignments_scanned']],
            ['Attendance pairs scanned', $report['attendance_pairs_scanned']],
            ['Assignment registrations created/planned', $report['registrations_created_from_assignments']],
            ['Attendance registrations created/planned', $report['registrations_created_from_attendance']],
            ['Already present', $report['registrations_already_present']],
            ['Attendance event conflicts', count($report['attendance_event_conflicts'])],
            ['Invalid company relationships', count($report['invalid_company_relationships'])],
            ['Registration status conflicts', count($report['registration_status_conflicts'])],
            ['Unregistered attendance pairs after run', $report['unregistered_attendance_pairs_after_run'] ?? 'dry-run'],
        ]);
        $this->line("Report: {$path}");
    }
}
