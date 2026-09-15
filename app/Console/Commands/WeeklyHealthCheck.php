<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Throwable;

class WeeklyHealthCheck extends Command
{
    protected $signature = 'health:weekly {--notify : Send the report even when all checks pass}';

    protected $description = 'Check production resources, queues, workers, HTTP health, and dependency security';

    public function handle(): int
    {
        $issues = [];
        $notes = [];

        $pendingJobs = DB::table('jobs')->count();
        $recentFailures = DB::table('failed_jobs')->where('failed_at', '>=', now()->subWeek())->count();
        $diskFreePercent = round((disk_free_space(base_path()) / disk_total_space(base_path())) * 100, 1);
        $memoryAvailablePercent = $this->memoryAvailablePercent();

        $notes[] = "Queue: {$pendingJobs} pending; {$recentFailures} failed in the last 7 days";
        $notes[] = "Disk: {$diskFreePercent}% free";
        $notes[] = 'Memory: '.($memoryAvailablePercent === null ? 'unavailable' : "{$memoryAvailablePercent}% available");

        if ($pendingJobs > config('services.health.queue_warning', 100)) {
            $issues[] = "Queue backlog is {$pendingJobs} jobs.";
        }
        if ($recentFailures > 0) {
            $issues[] = "{$recentFailures} queue jobs failed during the last 7 days.";
        }
        if ($diskFreePercent < config('services.health.disk_free_warning', 20)) {
            $issues[] = "Only {$diskFreePercent}% disk space remains.";
        }
        if ($memoryAvailablePercent !== null && $memoryAvailablePercent < config('services.health.memory_available_warning', 15)) {
            $issues[] = "Only {$memoryAvailablePercent}% memory is available.";
        }

        $this->checkWorkers($issues, $notes);
        $this->checkHttp($issues, $notes);
        $this->checkDependencies($issues, $notes);

        $status = $issues === [] ? 'HEALTHY' : 'ATTENTION NEEDED';
        $report = implode(PHP_EOL, [
            config('app.name').' weekly health check: '.$status,
            'Checked: '.now()->toIso8601String(),
            '',
            ...($issues === [] ? ['No operational or security problems found.'] : array_map(fn ($issue) => 'ISSUE: '.$issue, $issues)),
            '',
            ...$notes,
        ]);

        $issues === [] ? Log::info($report) : Log::warning($report);
        $this->line($report);

        if ($issues !== [] || $this->option('notify')) {
            $this->sendReport($report, $status);
        }

        return $issues === [] ? self::SUCCESS : self::FAILURE;
    }

    private function checkWorkers(array &$issues, array &$notes): void
    {
        $result = Process::timeout(15)->run(['sudo', 'supervisorctl', 'status', 'attendance-worker:*']);
        $output = trim($result->output().$result->errorOutput());
        $running = substr_count($output, 'RUNNING');
        $notes[] = "Queue workers: {$running} running";

        if (! $result->successful() || $running < config('services.health.minimum_workers', 1)) {
            $issues[] = 'Queue workers are not all running ('.($output ?: 'supervisor status unavailable').').';
        }
    }

    private function checkHttp(array &$issues, array &$notes): void
    {
        try {
            $response = Http::timeout(15)->get(config('app.url'));
            $notes[] = 'HTTP: '.$response->status();
            if (! $response->successful()) {
                $issues[] = 'Application HTTP check returned '.$response->status().'.';
            }
        } catch (Throwable $exception) {
            $issues[] = 'Application HTTP check failed: '.$exception->getMessage();
        }
    }

    private function checkDependencies(array &$issues, array &$notes): void
    {
        foreach ([
            'Composer' => ['composer', 'audit', '--locked', '--format=json', '--no-interaction'],
            'npm' => ['npm', 'audit', '--json'],
        ] as $name => $command) {
            $result = Process::path(base_path())->timeout(120)->run($command);
            $notes[] = "{$name} security audit: ".($result->successful() ? 'clear' : 'problems found');
            if (! $result->successful()) {
                $issues[] = "{$name} reported dependency vulnerabilities; review the health-check log.";
                Log::warning("{$name} audit output", ['output' => $result->output().$result->errorOutput()]);
            }
        }
    }

    private function sendReport(string $report, string $status): void
    {
        $configured = array_filter(array_map('trim', explode(',', (string) config('services.health.email'))));
        try {
            $recipients = $configured ?: User::role('admin')->whereNotNull('email')->pluck('email')->all();
        } catch (Throwable $exception) {
            Log::warning('Weekly health check could not look up platform admins.', ['exception' => $exception]);
            $recipients = $configured;
        }

        if ($recipients === []) {
            Log::warning('Weekly health check could not send email because no recipient is configured.');

            return;
        }

        try {
            Mail::raw($report, fn ($message) => $message->to($recipients)->subject(config('app.name')." health check: {$status}"));
        } catch (Throwable $exception) {
            Log::error('Weekly health-check email failed.', ['exception' => $exception]);
        }
    }

    private function memoryAvailablePercent(): ?float
    {
        if (! is_readable('/proc/meminfo')) {
            return null;
        }

        preg_match('/MemTotal:\s+(\d+)/', file_get_contents('/proc/meminfo'), $total);
        preg_match('/MemAvailable:\s+(\d+)/', file_get_contents('/proc/meminfo'), $available);

        return isset($total[1], $available[1]) ? round(($available[1] / $total[1]) * 100, 1) : null;
    }
}
