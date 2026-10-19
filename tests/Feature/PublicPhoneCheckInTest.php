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
    ])->assertSessionHas('error', 'This event has not started yet. QR check-in will open on '.now()->addDay()->format('M j, Y').'.');

    $this->assertDatabaseCount('attendances', 0);
});

it('lets attendees check themselves in on the shared link when the event allows it', function () {
    $registration = phoneCheckInRegistration();
    $event = $registration->event;
    $event->update(['self_check_in_enabled' => true]);
    $url = URL::signedRoute('scan.events', ['event' => $event->slug]);

    $this->get($url)->assertOk()
        ->assertSee('Enter the phone number you registered with to check yourself in.')
        ->assertSee(route('attendance.self-check', ['event' => $event->slug]), false);

    $this->from($url)->post(route('attendance.self-check', ['event' => $event->slug]), ['phone' => '020 123 4567', 'session' => 7])
        ->assertRedirect($url)
        ->assertSessionHas('success', 'Welcome, Akosua Mensah! Your Day 1 check-in is complete.');
    $this->post(route('attendance.self-check', ['event' => $event->slug]), ['phone' => '0201234567'])
        ->assertSessionHas('success', 'Akosua Mensah is already checked in for Day 1.');

    $attendance = Attendance::sole();
    expect($attendance->method)->toBe('self')
        ->and($attendance->marked_by)->toBeNull()
        ->and($attendance->session)->toBe($event->activeSessionNumber());
});

it('keeps self check-in off unless the event switches it on', function () {
    $registration = phoneCheckInRegistration();
    $event = $registration->event;

    $this->get(URL::signedRoute('scan.events', ['event' => $event->slug]))->assertOk()
        ->assertSee('Please give your registered phone number to an usher')
        ->assertDontSee(route('attendance.self-check', ['event' => $event->slug]), false);
    $this->post(route('attendance.self-check', ['event' => $event->slug]), ['phone' => '0201234567'])->assertNotFound();

    $this->assertDatabaseCount('attendances', 0);
});

it('turns down unknown numbers and closed days on self check-in without revealing anyone', function () {
    $registration = phoneCheckInRegistration();
    $event = $registration->event;
    $event->update(['self_check_in_enabled' => true]);

    $this->post(route('attendance.self-check', ['event' => $event->slug]), ['phone' => '0555555555'])
        ->assertSessionHas('error', 'We could not find a confirmed attendee with that phone number. Please check the number or ask an usher for help.');

    $event->update(['event_date' => now()->addDay()]);
    $this->post(route('attendance.self-check', ['event' => $event->slug]), ['phone' => '0201234567'])
        ->assertSessionHas('error', 'This event has not started yet. QR check-in will open on '.now()->addDay()->format('M j, Y').'.');

    $this->assertDatabaseCount('attendances', 0);
});

it('limits how fast one device can try numbers on self check-in', function () {
    $registration = phoneCheckInRegistration();
    $registration->event->update(['self_check_in_enabled' => true]);
    $url = route('attendance.self-check', ['event' => $registration->event->slug]);

    foreach (range(1, 10) as $attempt) {
        $this->post($url, ['phone' => '05000000'.str_pad((string) $attempt, 2, '0', STR_PAD_LEFT)]);
    }

    $this->post($url, ['phone' => '0201234567'])->assertTooManyRequests();
});

it('still shows staff their own check-in box when self check-in is on, and records them as the marker', function () {
    $registration = phoneCheckInRegistration();
    $event = $registration->event;
    $event->update(['self_check_in_enabled' => true]);
    $staff = staffFor($event);

    $this->actingAs($staff)->get(URL::signedRoute('scan.events', ['event' => $event->slug]))
        ->assertSee(route('attendance.check', ['event' => $event->slug]), false)
        ->assertDontSee(route('attendance.self-check', ['event' => $event->slug]), false);
    $this->post(route('attendance.check', ['event' => $event->slug]), ['phone' => '0201234567']);

    expect(Attendance::sole())->marked_by->toBe($staff->id)->method->toBeNull();
});

it('lets a manager switch self check-in on from the event settings', function () {
    $registration = phoneCheckInRegistration();
    $event = $registration->event;
    unlockAllEventFeatures($event);

    $this->actingAs(staffFor($event))->get(route('events.edit', $event))->assertOk()->assertSee('Let attendees check themselves in');
    $this->put(route('events.update', $event), [
        'title' => $event->title,
        'event_date' => $event->event_date->toDateString(),
        'self_check_in_enabled' => '1',
    ])->assertSessionHasNoErrors();

    expect($event->fresh()->self_check_in_enabled)->toBeTrue();
});
