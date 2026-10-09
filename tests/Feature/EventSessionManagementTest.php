<?php

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventSession;
use App\Models\Participant;
use App\Models\User;
use App\Services\AttendanceReportData;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function sessionsManager(Company $company): User
{
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    return $manager;
}

function sessionsEventWithAttendee(Company $company): array
{
    $event = Event::create(['company_id' => $company->id, 'title' => 'Multi-session Conference', 'event_date' => now()]);
    unlockAllEventFeatures($event);
    $participant = Participant::create(['company_id' => $company->id, 'name' => 'Attendee One']);
    $event->registrations()->create(['participant_id' => $participant->id, 'status' => EventRegistration::STATUS_CONFIRMED]);

    return [$event, $participant];
}

it('lets a manager add a second session to a day', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = sessionsManager($company);
    [$event] = sessionsEventWithAttendee($company);

    $this->actingAs($manager)
        ->post(route('events.sessions.store', $event), ['day' => 1, 'label' => 'Evening'])
        ->assertRedirect();

    $this->assertDatabaseHas('event_sessions', [
        'event_id' => $event->id,
        'day' => 1,
        'session' => 2,
        'label' => 'Evening',
    ]);
    expect($event->refresh()->sessionCountForDay(1))->toBe(2);
});

it('blocks removing a session that already has recorded attendance', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = sessionsManager($company);
    [$event, $participant] = sessionsEventWithAttendee($company);

    $eventSession = EventSession::create(['event_id' => $event->id, 'day' => 1, 'session' => 2, 'label' => 'Evening']);
    Attendance::create(['event_id' => $event->id, 'participant_id' => $participant->id, 'day' => 1, 'session' => 2, 'status' => 'present']);

    $this->actingAs($manager)
        ->delete(route('events.sessions.destroy', [$event, $eventSession]))
        ->assertRedirect();

    $this->assertDatabaseHas('event_sessions', ['id' => $eventSession->id]);
});

it('lets a manager remove an unused session', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = sessionsManager($company);
    [$event] = sessionsEventWithAttendee($company);
    $eventSession = EventSession::create(['event_id' => $event->id, 'day' => 1, 'session' => 2, 'label' => 'Evening']);

    $this->actingAs($manager)
        ->delete(route('events.sessions.destroy', [$event, $eventSession]))
        ->assertRedirect();

    $this->assertDatabaseMissing('event_sessions', ['id' => $eventSession->id]);
});

it('marks manual attendance against whichever session is set active', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = sessionsManager($company);
    [$event, $participant] = sessionsEventWithAttendee($company);
    EventSession::create(['event_id' => $event->id, 'day' => 1, 'session' => 2, 'label' => 'Evening']);

    $this->actingAs($manager)
        ->patch(route('events.update-day', $event), ['day' => 1, 'session' => 2])
        ->assertRedirect();

    expect($event->refresh()->session)->toBe(2);

    $this->actingAs($manager)
        ->post(route('events.attendance.store', $event), ['participant_id' => $participant->id])
        ->assertRedirect();

    $this->assertDatabaseHas('attendances', [
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'day' => 1,
        'session' => 2,
    ]);
    $this->assertDatabaseMissing('attendances', [
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'day' => 1,
        'session' => 1,
    ]);
});

it('treats a day as attended if present at either session, but a session filter narrows to just that one', function () {
    $company = Company::create(['name' => 'Acme Co']);
    [$event] = sessionsEventWithAttendee($company);
    $morningOnly = Participant::create(['company_id' => $company->id, 'name' => 'Morning Only']);
    $event->registrations()->create(['participant_id' => $morningOnly->id, 'status' => EventRegistration::STATUS_CONFIRMED]);
    $eveningOnly = Participant::create(['company_id' => $company->id, 'name' => 'Evening Only']);
    $event->registrations()->create(['participant_id' => $eveningOnly->id, 'status' => EventRegistration::STATUS_CONFIRMED]);

    Attendance::create(['event_id' => $event->id, 'participant_id' => $morningOnly->id, 'day' => 1, 'session' => 1, 'status' => 'present']);
    Attendance::create(['event_id' => $event->id, 'participant_id' => $eveningOnly->id, 'day' => 1, 'session' => 2, 'status' => 'present']);

    $service = app(AttendanceReportData::class);

    $dayLevel = $service->forPeriod($event, 1);
    expect($dayLevel['presentUsers']->pluck('name')->sort()->values()->all())->toBe(['Evening Only', 'Morning Only']);

    $sessionTwo = $service->forPeriod($event, 1, 2);
    expect($sessionTwo['presentUsers']->pluck('name')->all())->toBe(['Evening Only'])
        ->and($sessionTwo['absentUsers']->pluck('name'))->toContain('Morning Only');
});

it('rejects an out of range session for the attendance export', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = sessionsManager($company);
    [$event] = sessionsEventWithAttendee($company);

    $this->actingAs($manager)
        ->get(route('reports.csv', ['event' => $event, 'day' => 1, 'session' => 99]))
        ->assertSessionHasErrors('session');
});

it('keeps both sessions attendance when merging participants who attended different sessions the same day', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = sessionsManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Merge Event', 'event_date' => now()]);

    $primary = Participant::create(['company_id' => $company->id, 'name' => 'Jane Doe', 'phone' => '201234567']);
    $duplicate = Participant::create(['company_id' => $company->id, 'name' => 'Jane D', 'phone' => '201234567']);

    Attendance::create(['event_id' => $event->id, 'participant_id' => $primary->id, 'day' => 1, 'session' => 1, 'status' => 'present']);
    Attendance::create(['event_id' => $event->id, 'participant_id' => $duplicate->id, 'day' => 1, 'session' => 2, 'status' => 'present']);

    $this->actingAs($manager)
        ->post(route('participants.duplicates.merge'), [
            'primary_id' => $primary->id,
            'all_ids' => [$primary->id, $duplicate->id],
        ])
        ->assertRedirect(route('participants.duplicates.index'));

    expect(Attendance::where('event_id', $event->id)->where('participant_id', $primary->id)->count())->toBe(2);
});
