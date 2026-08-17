<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

it('recognizes that the legacy participant migration is complete', function () {
    expect(Schema::hasColumn('users', 'event_id'))->toBeFalse()
        ->and(Artisan::call('participants:migrate-registrations'))->toBe(0)
        ->and(Artisan::output())->toContain('Phase 3 is complete');
});
