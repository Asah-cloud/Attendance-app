<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function arrivalEvent(): array
{
    $company = Company::create(['name' => 'Arrival Company']);
    $event = Event::create([
        'company_id' => $company->id,
        'title' => 'Arrival Conference',
        'event_date' => now(),
        'end_date' => now()->addDay(),
        'has_arrival_session' => true,
        'arrival_date' => now(),
        'day' => 1,
    ]);
    $participant = Participant::create([
        'company_id' => $company->id,
        'name' => 'Arrival Guest',
        'phone' => '241234567',
    ]);
    $event->registrations()->create([
        'participant_id' => $participant->id,
        'status' => EventRegistration::STATUS_CONFIRMED,
    ]);

    return [$company, $event, $participant];
}

it('lets a manager configure arrival on the same date as day one', function () {
    $company = Company::create(['name' => 'Configured Company']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $date = now()->addWeek()->toDateString();

    $this->actingAs($manager)->post(route('events.store'), [
        'title' => 'Same-day Arrival',
        'event_date' => $date,
        'has_arrival_session' => 1,
        'arrival_date' => $date,
    ])->assertRedirect('/events');

    $this->assertDatabaseHas('events', [
        'title' => 'Same-day Arrival',
        'has_arrival_session' => true,
        'arrival_date' => $date.' 00:00:00',
        'day' => 1,
    ]);
});

it('records arrival separately and then allows day one attendance', function () {
    [$company, $event, $participant] = arrivalEvent();
    $arrivalUrl = URL::signedRoute('arrival.check', ['event' => $event->slug]);

    $this->post($arrivalUrl, ['phone' => '0241234567'])
        ->assertSessionHas('success', 'Welcome, Arrival Guest! Your Arrival check-in is complete.');

    $attendanceUrl = URL::signedRoute('attendance.check', ['event' => $event->slug]);
    $this->post($attendanceUrl, ['phone' => '0241234567'])
        ->assertSessionHas('success', 'Welcome, Arrival Guest! Your Day 1 check-in is complete.');

    $this->assertDatabaseHas('attendances', ['event_id' => $event->id, 'participant_id' => $participant->id, 'day' => 0]);
    $this->assertDatabaseHas('attendances', ['event_id' => $event->id, 'participant_id' => $participant->id, 'day' => 1]);
});

it('provides a separate arrival report', function () {
    [$company, $event] = arrivalEvent();
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $this->post(URL::signedRoute('arrival.check', ['event' => $event->slug]), ['phone' => '0241234567']);

    $this->actingAs($manager)
        ->get(route('reports.event', ['event' => $event, 'day' => 0]))
        ->assertOk()
        ->assertSee('Arrival')
        ->assertSee('Arrival Guest');
});

it('shows confirmed members in the arrival workspace before they arrive', function () {
    [$company, $event] = arrivalEvent();
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    $this->actingAs($manager)
        ->get(route('events.arrival', $event))
        ->assertOk()
        ->assertSee('Arrival Guest')
        ->assertSee('Yet to arrive')
        ->assertSee('Open Arrival scanner');
});

it('gates daily QR attendance until arrival has been checked in', function () {
    [$company, $event, $participant] = arrivalEvent();
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $registration = $event->registrations()->where('participant_id', $participant->id)->firstOrFail();

    $this->actingAs($manager)
        ->postJson(route('events.scanner.check-in', $event), ['registration_code' => $registration->registration_code])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Arrival Guest has not completed Arrival check-in yet.');

    $this->postJson(route('events.arrival.scanner.check-in', $event), ['registration_code' => $registration->registration_code])
        ->assertOk()
        ->assertJsonPath('day', 0);

    $this->postJson(route('events.scanner.check-in', $event), ['registration_code' => $registration->registration_code])
        ->assertOk()
        ->assertJsonPath('day', 1);
});

it('does not allow arrival when the event has no arrival session', function () {
    $event = Event::create(['title' => 'Regular Event', 'event_date' => now(), 'day' => 1]);

    expect($event->canMarkAttendanceForDay(0))->toBeFalse();
});
