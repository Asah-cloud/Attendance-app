<?php

use App\Imports\HardCopyContactsImport;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\AttendanceConfirmationRequest;
use App\Notifications\RegistrationLifecycleNotification;
use Illuminate\Support\Facades\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function hardCopyManager(Company $company): User
{
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    return $manager;
}

it('imports hard-copy contacts as awaiting confirmation and keeps them out of confirmed reports', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Homecoming', 'event_date' => now()]);

    Excel::import(new HardCopyContactsImport($event), base_path('tests/Fixtures/hardcopy_contacts.csv'));

    $john = Participant::where('email', 'john@example.com')->firstOrFail();
    $jane = Participant::where('phone', '209999999')->firstOrFail();

    expect($event->registrations()->where('participant_id', $john->id)->firstOrFail()->status)
        ->toBe(EventRegistration::STATUS_AWAITING_CONFIRMATION)
        ->and($event->confirmedParticipants()->whereKey($john->id)->exists())->toBeFalse()
        ->and($event->confirmedParticipants()->whereKey($jane->id)->exists())->toBeFalse();
});

it('does not disturb an existing registration when the same contact is re-imported', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Homecoming', 'event_date' => now()]);
    $participant = Participant::create(['company_id' => $company->id, 'name' => 'John Hardcopy', 'email' => 'john@example.com']);
    $event->registrations()->create(['participant_id' => $participant->id, 'status' => EventRegistration::STATUS_CONFIRMED]);

    Excel::import(new HardCopyContactsImport($event), base_path('tests/Fixtures/hardcopy_contacts.csv'));

    expect($event->registrations()->where('participant_id', $participant->id)->firstOrFail()->status)
        ->toBe(EventRegistration::STATUS_CONFIRMED);
});

it('lets a manager customize the welcome message and bulk send confirmation requests', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Acme Co']);
    $manager = hardCopyManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Homecoming', 'event_date' => now()]);
    $participant = Participant::create(['company_id' => $company->id, 'name' => 'John Hardcopy', 'email' => 'john@example.com', 'phone' => '201234567']);
    $registration = $event->registrations()->create(['participant_id' => $participant->id, 'status' => EventRegistration::STATUS_AWAITING_CONFIRMATION]);

    $this->actingAs($manager)
        ->patch(route('events.confirmations.message.update', $event), ['confirmation_message' => 'Hey {name}, thanks for joining {event}!'])
        ->assertSessionHas('success');
    expect($event->fresh()->confirmation_message)->toBe('Hey {name}, thanks for joining {event}!');

    $this->actingAs($manager)
        ->get(route('events.confirmations.index', $event))
        ->assertOk()
        ->assertSee('John Hardcopy');

    $this->actingAs($manager)
        ->post(route('events.confirmations.send', $event))
        ->assertSessionHas('success');

    Notification::assertSentTo($participant, AttendanceConfirmationRequest::class, function (AttendanceConfirmationRequest $notification) use ($participant) {
        return str_contains($notification->toArkesel($participant), 'Acme Co');
    });
    expect($registration->fresh()->confirmation_sent_at)->not->toBeNull();
});

it('lets a manager add a confirmation question inline and preview the live form', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = hardCopyManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Homecoming', 'event_date' => now()]);
    $participant = Participant::create(['company_id' => $company->id, 'name' => 'John Hardcopy']);
    $registration = $event->registrations()->create(['participant_id' => $participant->id, 'status' => EventRegistration::STATUS_AWAITING_CONFIRMATION]);

    $this->actingAs($manager)
        ->post(route('events.registration-fields.store', $event), [
            'label' => 'Dietary restrictions',
            'field_type' => 'text',
        ])
        ->assertSessionHas('success');

    $this->actingAs($manager)
        ->get(route('events.confirmations.index', $event))
        ->assertOk()
        ->assertSee('Dietary restrictions')
        ->assertSee(route('attendance.confirm.show', $registration->registration_code), false);
});

it('lets an attendee confirm their attendance through the personal link', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Acme Co']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Homecoming', 'event_date' => now(), 'registration_terms' => 'Standard terms.']);
    $participant = Participant::create(['company_id' => $company->id, 'name' => 'John Hardcopy', 'email' => 'john@example.com']);
    $registration = $event->registrations()->create(['participant_id' => $participant->id, 'status' => EventRegistration::STATUS_AWAITING_CONFIRMATION, 'source' => 'hardcopy_import']);

    $this->get(route('attendance.confirm.show', $registration->registration_code))
        ->assertOk()
        ->assertSee('John Hardcopy')
        ->assertSee('Homecoming')
        ->assertSee('Acme Co')
        ->assertSee("You're invited", false);

    $this->post(route('attendance.confirm.store', $registration->registration_code))
        ->assertSessionHasErrors('consent');
    expect($registration->fresh()->status)->toBe(EventRegistration::STATUS_AWAITING_CONFIRMATION);

    $this->post(route('attendance.confirm.store', $registration->registration_code), ['consent' => '1'])
        ->assertRedirect(route('registrations.confirmation', $registration->registration_code));

    expect($registration->fresh()->status)->toBe(EventRegistration::STATUS_CONFIRMED)
        ->and($event->confirmedParticipants()->whereKey($participant->id)->exists())->toBeTrue();

    Notification::assertSentTo(
        $participant,
        RegistrationLifecycleNotification::class,
        fn ($notification) => $notification->type === 'confirmed'
    );

    // Following the confirmation through shows the "thanks" copy and the scannable QR.
    $this->get(route('registrations.confirmation', $registration->registration_code))
        ->assertOk()
        ->assertSee('Thanks for confirming!')
        ->assertSee('Your personal check-in QR');

    // Revisiting the original confirm link after they've already confirmed should
    // land them back on their QR page, not a dead 404.
    $this->get(route('attendance.confirm.show', $registration->registration_code))
        ->assertRedirect(route('registrations.confirmation', $registration->registration_code));
    $this->post(route('attendance.confirm.store', $registration->registration_code), ['consent' => '1'])
        ->assertRedirect(route('registrations.confirmation', $registration->registration_code));
});

it('redirects a registration that is not awaiting confirmation to its QR page instead of showing the form', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Homecoming', 'event_date' => now()]);
    $participant = Participant::create(['company_id' => $company->id, 'name' => 'Already Confirmed']);
    $registration = $event->registrations()->create(['participant_id' => $participant->id, 'status' => EventRegistration::STATUS_CONFIRMED]);

    $this->get(route('attendance.confirm.show', $registration->registration_code))
        ->assertRedirect(route('registrations.confirmation', $registration->registration_code));
});

it('404s for a confirmation code that does not exist at all', function () {
    $this->get(route('attendance.confirm.show', 'not-a-real-code'))->assertNotFound();
});

it('lets a manager remove an imported contact without notifying them', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Acme Co']);
    $manager = hardCopyManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Homecoming', 'event_date' => now()]);
    $participant = Participant::create(['company_id' => $company->id, 'name' => 'John Hardcopy', 'email' => 'john@example.com']);
    $registration = $event->registrations()->create(['participant_id' => $participant->id, 'status' => EventRegistration::STATUS_AWAITING_CONFIRMATION]);

    $this->actingAs($manager)
        ->delete(route('events.confirmations.destroy', [$event, $registration]))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('event_registrations', ['id' => $registration->id]);
    Notification::assertNothingSent();
});

it('prevents an usher from reaching the confirmations section', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $usher = User::factory()->create(['company_id' => $company->id, 'role' => 'usher']);
    $usher->assignRole('usher');
    $event = Event::create(['company_id' => $company->id, 'title' => 'Homecoming', 'event_date' => now()]);

    $this->actingAs($usher)->get(route('events.confirmations.index', $event))->assertForbidden();
});

it('prevents a manager from reaching another company confirmations section', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $otherCompany = Company::create(['name' => 'Other Co']);
    $manager = hardCopyManager($company);
    $event = Event::create(['company_id' => $otherCompany->id, 'title' => 'Homecoming', 'event_date' => now()]);

    $this->actingAs($manager)->get(route('events.confirmations.index', $event))->assertForbidden();
});
