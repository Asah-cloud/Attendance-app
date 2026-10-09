<?php

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Event;
use App\Models\Participant;

function sessionColumnEvent(): array
{
    $company = Company::create(['name' => 'Session Column Company']);
    $event = Event::create([
        'company_id' => $company->id,
        'title' => 'Session Column Conference',
        'event_date' => now(),
        'end_date' => now()->addDay(),
        'day' => 1,
    ]);
    $participant = Participant::create([
        'company_id' => $company->id,
        'name' => 'Session Column Guest',
        'phone' => '241234999',
    ]);

    return [$event, $participant];
}

it('allows two attendance rows for the same day when the session differs', function () {
    [$event, $participant] = sessionColumnEvent();

    Attendance::create([
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'day' => 1,
        'session' => 1,
        'status' => 'present',
    ]);

    Attendance::create([
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'day' => 1,
        'session' => 2,
        'status' => 'present',
    ]);

    expect(Attendance::where('event_id', $event->id)->where('participant_id', $participant->id)->where('day', 1)->count())->toBe(2);
});

it('still enforces one attendance row per day when the session matches', function () {
    [$event, $participant] = sessionColumnEvent();

    Attendance::create([
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'day' => 1,
        'session' => 1,
        'status' => 'present',
    ]);

    expect(fn () => Attendance::create([
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'day' => 1,
        'session' => 1,
        'status' => 'present',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('defaults new attendance rows to session 1', function () {
    [$event, $participant] = sessionColumnEvent();

    $attendance = Attendance::create([
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'day' => 1,
        'status' => 'present',
    ]);

    expect($attendance->refresh()->session)->toBe(1);
});

it('keeps the day label unchanged for session 1 and appends the session number otherwise', function () {
    [$event] = sessionColumnEvent();

    expect($event->attendanceSessionLabel(1))->toBe('Day 1');
    expect($event->attendanceSessionLabel(1, 1))->toBe('Day 1');
    expect($event->attendanceSessionLabel(1, 2))->toBe('Day 1 · Session 2');
    expect($event->attendanceSessionLabel(0))->toBe('Arrival');
    expect($event->attendanceSessionLabel('all'))->toBe('All sessions');
});
