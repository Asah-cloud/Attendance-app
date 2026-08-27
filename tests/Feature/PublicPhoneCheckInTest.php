<?php

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use Illuminate\Support\Facades\URL;

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

it('shows the shared check-in page only with a valid event signature', function () {
    $registration = phoneCheckInRegistration();

    $this->get(URL::signedRoute('scan.events', ['event' => $registration->event_id]))
        ->assertOk()
        ->assertSee('Members, ushers, and managers can all use this page.');

    $this->get(route('scan.events', $registration->event_id))->assertForbidden();
});

it('checks in a confirmed attendee using common Ghana phone formats', function () {
    $registration = phoneCheckInRegistration();
    $url = URL::signedRoute('attendance.check', ['event' => $registration->event_id]);

    $this->post($url, ['phone' => '+233 20 123 4567'])
        ->assertRedirect()
        ->assertSessionHas('success', 'Welcome, Akosua Mensah! Your Day 1 check-in is complete.');

    $this->assertDatabaseHas('attendances', [
        'event_id' => $registration->event_id,
        'participant_id' => $registration->participant_id,
        'day' => 1,
        'marked_by' => null,
    ]);
});

it('does not duplicate a phone check-in', function () {
    $registration = phoneCheckInRegistration();
    $url = URL::signedRoute('attendance.check', ['event' => $registration->event_id]);

    $this->post($url, ['phone' => '0201234567']);
    $this->post($url, ['phone' => '201234567'])
        ->assertSessionHas('success', 'Akosua Mensah is already checked in for Day 1.');

    expect(Attendance::query()->where('event_id', $registration->event_id)->count())->toBe(1);
});

it('rejects unknown and unconfirmed phone numbers', function () {
    $registration = phoneCheckInRegistration(status: EventRegistration::STATUS_PENDING);
    $url = URL::signedRoute('attendance.check', ['event' => $registration->event_id]);

    $this->post($url, ['phone' => '0201234567'])->assertSessionHas('error');
    $this->post($url, ['phone' => '0555555555'])->assertSessionHas('error');

    $this->assertDatabaseCount('attendances', 0);
});

it('does not check anyone in outside the active event dates', function () {
    $registration = phoneCheckInRegistration();
    $registration->event->update(['event_date' => now()->addDay()]);

    $this->post(URL::signedRoute('attendance.check', ['event' => $registration->event_id]), [
        'phone' => '0201234567',
    ])->assertSessionHas('error', 'Attendance is not open for this event right now. Please ask an event manager for help.');

    $this->assertDatabaseCount('attendances', 0);
});
