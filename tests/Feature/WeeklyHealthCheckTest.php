<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

it('reports healthy infrastructure and dependency audits', function () {
    config()->set('services.health.disk_free_warning', 0);
    config()->set('services.health.memory_available_warning', 0);
    config()->set('services.health.minimum_workers', 0);
    Http::fake([config('app.url') => Http::response('OK')]);
    Process::fake([
        '*' => Process::result(
            output: "attendance-worker:attendance-worker_00 RUNNING\nattendance-worker:attendance-worker_01 RUNNING\n"
        ),
    ]);

    $this->artisan('health:weekly')->assertSuccessful();
});
