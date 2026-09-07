<?php

use App\Models\Attendance;
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

function phoneCheckInRegistration(string $phone = '201234567', string $status = EventRegistration::STATUS_CONFIRMED): EventRegistration
{
    $company = Company::create(['name' => 'Phone Check-in Company']);
    $event = Event::create([
        'company_id' => $company->id,
        'title' => 'Arrival Event',
        'event_date' => now(),
    ]);
    $participant = Participant::create([
        'company_id' => $company->id,
        'name' => 'Akosua Mensah',
        'phone' => $phone,
    ]);

    return $event->registrations()->create([
        'participant_id' => $participant->id,
        'status' => $status,
    ]);
}

function staffFor(Event $event): User
{
    $user = User::factory()->create(['company_id' => $event->company_id, 'role' => 'manager']);
    $user->assignRole('manager');

    return $user;
}

it('shows the shared check-in page only with a valid event signature', function () {
    $registration = phoneCheckInRegistration();
    $url = URL::signedRoute('scan.events', ['event' => $registration->event->slug]);

    expect($url)
        ->toContain('/events/arrival-event/public-check-in')
        ->not->toContain('/events/'.$registration->event_id.'/public-check-in');

    $this->get($url)
        ->assertOk()
        ->assertSee('Please give your registered phone number to an usher');

    $this->get(route('scan.events', $registration->event->slug))->assertForbidden();
});

it('does not allow an unauthenticated visitor to check someone in by phone', function () {
    $registration = phoneCheckInRegistration();

    $this->post(route('attendance.check', ['event' => $registration->event->slug]), ['phone' => '0201234567'])
        ->assertRedirect(route('login'));

    $this->assertDatabaseCount('attendances', 0);
});

it('lets staff check in a confirmed attendee using common Ghana phone formats', function () {
    $registration = phoneCheckInRegistration();
    $staff = staffFor($registration->event);

    $this->actingAs($staff)
        ->post(route('attendance.check', ['event' => $registration->event->slug]), ['phone' => '+233 20 123 4567'])
        ->assertRedirect()
        ->assertSessionHas('success', 'Welcome, Akosua Mensah! Your Day 1 check-in is complete.');

    $this->assertDatabaseHas('attendances', [
        'event_id' => $registration->event_id,
        'participant_id' => $registration->participant_id,
        'day' => 1,
        'marked_by' => $staff->id,
    ]);
});

it('does not duplicate a phone check-in', function () {
    $registration = phoneCheckInRegistration();
    $staff = staffFor($registration->event);

    $this->actingAs($staff)->post(route('attendance.check', ['event' => $registration->event->slug]), ['phone' => '0201234567']);
    $this->actingAs($staff)->post(route('attendance.check', ['event' => $registration->event->slug]), ['phone' => '201234567'])
        ->assertSessionHas('success', 'Akosua Mensah is already checked in for Day 1.');

    expect(Attendance::query()->where('event_id', $registration->event_id)->count())->toBe(1);
});

it('rejects unknown and unconfirmed phone numbers', function () {
    $registration = phoneCheckInRegistration(status: EventRegistration::STATUS_PENDING);
    $staff = staffFor($registration->event);

    $this->actingAs($staff)->post(route('attendance.check', ['event' => $registration->event->slug]), ['phone' => '0201234567'])->assertSessionHas('error');
    $this->actingAs($staff)->post(route('attendance.check', ['event' => $registration->event->slug]), ['phone' => '0555555555'])->assertSessionHas('error');

    $this->assertDatabaseCount('attendances', 0);
});

it('does not check anyone in outside the active event dates', function () {
    $registration = phoneCheckInRegistration();
    $registration->event->update(['event_date' => now()->addDay()]);
    $staff = staffFor($registration->event);

    $this->actingAs($staff)->post(route('attendance.check', ['event' => $registration->event->slug]), [
        'phone' => '0201234567',
    ])->assertSessionHas('error', 'Attendance is not open for this event right now. Please ask an event manager for help.');

    $this->assertDatabaseCount('attendances', 0);
});
