<?php

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Services\ApplicationCache;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

it('reuses cached event data until attendance changes', function () {
    $company = Company::create(['name' => 'Cache Company']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Cache Event', 'event_date' => now()]);
    $participant = Participant::create(['company_id' => $company->id, 'name' => 'Cached Person']);
    $runs = 0;
    $cache = app(ApplicationCache::class);

    $first = $cache->rememberEvent($event->id, 'test-stats', function () use (&$runs) {
        $runs++;

        return 'first';
    });
    $second = $cache->rememberEvent($event->id, 'test-stats', function () use (&$runs) {
        $runs++;

        return 'second';
    });

    expect($first)->toBe('first')->and($second)->toBe('first')->and($runs)->toBe(1);

    Attendance::create(['event_id' => $event->id, 'participant_id' => $participant->id, 'day' => 1, 'status' => 'present']);

    $fresh = $cache->rememberEvent($event->id, 'test-stats', function () use (&$runs) {
        $runs++;

        return 'fresh';
    });

    expect($fresh)->toBe('fresh')->and($runs)->toBe(2);
});

it('invalidates company dashboard data when a registration changes', function () {
    $company = Company::create(['name' => 'Registration Cache Company']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Registration Event', 'event_date' => now()]);
    $participant = Participant::create(['company_id' => $company->id, 'name' => 'New Attendee']);
    $cache = app(ApplicationCache::class);

    expect($cache->rememberCompany($company->id, 'registration-count', fn () => EventRegistration::where('event_id', $event->id)->count()))->toBe(0);

    EventRegistration::create([
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'status' => EventRegistration::STATUS_CONFIRMED,
    ]);

    expect($cache->rememberCompany($company->id, 'registration-count', fn () => EventRegistration::where('event_id', $event->id)->count()))->toBe(1);
});
