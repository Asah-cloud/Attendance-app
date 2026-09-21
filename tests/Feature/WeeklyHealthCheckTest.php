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
            output: 'php '.base_path('artisan')." queue:work --queue=badges,default\nphp ".base_path('artisan')." queue:work --queue=badges,default\n"
        ),
    ]);

    $this->artisan('health:weekly')->assertSuccessful();
    Process::assertRan(fn ($process) => $process->command === ['ps', '-eo', 'args=']);
});

it('reports when fewer queue worker processes than required are running', function () {
    config()->set('services.health.disk_free_warning', 0);
    config()->set('services.health.memory_available_warning', 0);
    config()->set('services.health.minimum_workers', 2);
    Http::fake([config('app.url') => Http::response('OK')]);
    Process::fake(['*' => Process::result(output: 'php '.base_path('artisan')." queue:work --queue=default\n")]);

    $this->artisan('health:weekly')->assertFailed();
});
