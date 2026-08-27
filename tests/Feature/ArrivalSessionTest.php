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
        'day' => 0,
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
        'day' => 0,
    ]);
});

it('records arrival separately and then allows day one attendance', function () {
    [$company, $event, $participant] = arrivalEvent();
    $url = URL::signedRoute('attendance.check', ['event' => $event->id]);

    $this->post($url, ['phone' => '0241234567'])
        ->assertSessionHas('success', 'Welcome, Arrival Guest! Your Arrival check-in is complete.');

    $event->update(['day' => 1]);
    $this->post($url, ['phone' => '0241234567'])
        ->assertSessionHas('success', 'Welcome, Arrival Guest! Your Day 1 check-in is complete.');

    $this->assertDatabaseHas('attendances', ['event_id' => $event->id, 'participant_id' => $participant->id, 'day' => 0]);
    $this->assertDatabaseHas('attendances', ['event_id' => $event->id, 'participant_id' => $participant->id, 'day' => 1]);
});

it('provides a separate arrival report', function () {
    [$company, $event] = arrivalEvent();
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $this->post(URL::signedRoute('attendance.check', ['event' => $event->id]), ['phone' => '0241234567']);

    $this->actingAs($manager)
        ->get(route('reports.event', ['event' => $event, 'day' => 0]))
        ->assertOk()
        ->assertSee('Arrival')
        ->assertSee('Arrival Guest');
});

it('does not allow arrival when the event has no arrival session', function () {
    $event = Event::create(['title' => 'Regular Event', 'event_date' => now(), 'day' => 1]);

    expect($event->canMarkAttendanceForDay(0))->toBeFalse();
});
