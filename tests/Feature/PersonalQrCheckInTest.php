<?php

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function personalQrRegistration(array $eventOverrides = [], string $status = EventRegistration::STATUS_CONFIRMED): EventRegistration
{
    $company = Company::create(['name' => 'QR Company']);
    $event = Event::create(array_merge([
        'company_id' => $company->id,
        'title' => 'QR Event',
        'event_date' => now(),
    ], $eventOverrides));
    $participant = Participant::create([
        'company_id' => $company->id,
        'name' => 'Ama Attendee',
        'phone' => '201234567',
    ]);

    return $event->registrations()->create([
        'participant_id' => $participant->id,
        'status' => $status,
    ]);
}

function qrStaff(EventRegistration $registration, string $role = 'manager', ?Company $company = null): User
{
    $user = User::factory()->create([
        'company_id' => ($company ?? $registration->event->company)->id,
        'role' => $role,
    ]);
    $user->assignRole($role);

    return $user;
}

it('never records attendance when the public QR link is opened', function () {
    $registration = personalQrRegistration();

    $this->get(route('attendance.personal', $registration->registration_code))
        ->assertOk()
        ->assertSee('Staff scan required')
        ->assertSee('You are all set!');

    $this->assertDatabaseCount('attendances', 0);
});

it('requires authentication for the secure scanner', function () {
    $registration = personalQrRegistration();

    $this->get(route('events.scanner', $registration->event))->assertRedirect(route('login'));
    $this->postJson(route('events.scanner.check-in', $registration->event), [
        'registration_code' => $registration->registration_code,
    ])->assertUnauthorized();
});

it('allows a company manager to scan a confirmed attendee', function () {
    $registration = personalQrRegistration();
    $manager = qrStaff($registration);

    $this->actingAs($manager)
        ->postJson(route('events.scanner.check-in', $registration->event), [
            'registration_code' => 'ASAH-ATTENDANCE:'.$registration->registration_code,
        ])
        ->assertOk()
        ->assertJsonPath('successful', true)
        ->assertJsonPath('name', 'Ama Attendee');

    $this->assertDatabaseHas('attendances', [
        'event_id' => $registration->event_id,
        'participant_id' => $registration->participant_id,
        'day' => 1,
        'marked_by' => $manager->id,
    ]);
});

it('allows only ushers assigned to the event to scan', function () {
    $registration = personalQrRegistration();
    $assigned = qrStaff($registration, 'usher');
    $unassigned = qrStaff($registration, 'usher');
    $assigned->events()->attach($registration->event);

    $this->actingAs($unassigned)
        ->postJson(route('events.scanner.check-in', $registration->event), [
            'registration_code' => $registration->registration_code,
        ])->assertForbidden();

    $this->actingAs($assigned)
        ->postJson(route('events.scanner.check-in', $registration->event), [
            'registration_code' => $registration->registration_code,
        ])->assertOk();
});

it('prevents a manager from scanning for another company', function () {
    $registration = personalQrRegistration();
    $otherCompany = Company::create(['name' => 'Other Company']);
    $manager = qrStaff($registration, 'manager', $otherCompany);

    $this->actingAs($manager)
        ->postJson(route('events.scanner.check-in', $registration->event), [
            'registration_code' => $registration->registration_code,
        ])->assertForbidden();
});

it('rejects a code belonging to another event and unconfirmed registrations', function () {
    $registration = personalQrRegistration();
    $manager = qrStaff($registration);
    $other = personalQrRegistration();
    $pending = $registration->event->registrations()->create([
        'participant_id' => Participant::create([
            'company_id' => $registration->event->company_id,
            'name' => 'Pending Person',
        ])->id,
        'status' => EventRegistration::STATUS_PENDING,
    ]);

    $this->actingAs($manager)
        ->postJson(route('events.scanner.check-in', $registration->event), [
            'registration_code' => $other->registration_code,
        ])->assertUnprocessable();

    $this->postJson(route('events.scanner.check-in', $registration->event), [
        'registration_code' => $pending->registration_code,
    ])->assertUnprocessable();

    $this->assertDatabaseCount('attendances', 0);
});

it('records repeated scans only once', function () {
    $registration = personalQrRegistration();
    $manager = qrStaff($registration);
    $url = route('events.scanner.check-in', $registration->event);

    $this->actingAs($manager)->postJson($url, ['registration_code' => $registration->registration_code])
        ->assertJsonPath('already_present', false);
    $this->postJson($url, ['registration_code' => $registration->registration_code])
        ->assertJsonPath('already_present', true);

    expect(Attendance::query()->where('event_id', $registration->event_id)->count())->toBe(1);
});

it('does not reveal whether an unknown public QR token exists', function () {
    $this->get(route('attendance.personal', str_repeat('x', 40)))->assertNotFound();
});
