<?php

use App\Models\AccommodationRoom;
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

function clearRegistration(Event $event, string $name, string $status = EventRegistration::STATUS_CONFIRMED): EventRegistration
{
    $participant = Participant::create(['company_id' => $event->company_id, 'name' => $name, 'phone' => (string) random_int(200000000, 299999999)]);

    return EventRegistration::create(['event_id' => $event->id, 'participant_id' => $participant->id, 'status' => $status]);
}

it('deletes every registration for the event except checked-in attendees', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = User::factory()->create(['company_id' => $company->id]);
    $manager->assignRole('manager');
    $event = Event::create(['company_id' => $company->id, 'title' => 'Annual Summit', 'event_date' => now()]);
    $other = Event::create(['company_id' => $company->id, 'title' => 'Other Event', 'event_date' => now()]);

    $plain = clearRegistration($event, 'Plain Guest');
    $withRoom = clearRegistration($event, 'Room Guest');
    $checkedIn = clearRegistration($event, 'Arrived Guest');
    $elsewhere = clearRegistration($other, 'Other Guest');

    $site = $event->accommodationSites()->create(['name' => 'Campus']);
    $room = $site->blocks()->create(['name' => 'B'])->floors()->create(['name' => 'F'])->rooms()->create(['name' => 'R1', 'capacity' => 2]);
    $assignmentId = $withRoom->roomAssignment()->create(['accommodation_room_id' => $room->id, 'status' => 'assigned', 'method' => 'manual'])->id;
    Attendance::create(['event_id' => $event->id, 'participant_id' => $checkedIn->participant_id, 'day' => 1, 'status' => 'present']);

    $this->actingAs($manager)
        ->delete(route('events.registrations.destroy-all', $event), ['confirm_title' => 'Annual Summit'])
        ->assertRedirect(route('events.registrations.index', $event));

    expect(EventRegistration::find($plain->id))->toBeNull()
        ->and(EventRegistration::find($withRoom->id))->toBeNull()
        ->and(EventRegistration::find($checkedIn->id))->not->toBeNull()
        ->and(EventRegistration::find($elsewhere->id))->not->toBeNull();

    $this->assertDatabaseMissing('room_assignments', ['id' => $assignmentId]);
    expect(AccommodationRoom::find($room->id))->not->toBeNull();
    expect(Participant::find($plain->participant_id))->not->toBeNull();
});

it('does nothing when the typed title does not match', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = User::factory()->create(['company_id' => $company->id]);
    $manager->assignRole('manager');
    $event = Event::create(['company_id' => $company->id, 'title' => 'Annual Summit', 'event_date' => now()]);
    clearRegistration($event, 'Guest A');
    clearRegistration($event, 'Guest B');

    $this->actingAs($manager)
        ->from(route('events.registrations.index', $event))
        ->delete(route('events.registrations.destroy-all', $event), ['confirm_title' => 'annual summit'])
        ->assertSessionHasErrors('confirm_title');

    expect($event->registrations()->count())->toBe(2);
});

it('forbids an usher from clearing attendees', function () {
    $company = Company::create(['name' => 'Acme']);
    $usher = User::factory()->create(['company_id' => $company->id]);
    $usher->assignRole('usher');
    $event = Event::create(['company_id' => $company->id, 'title' => 'Annual Summit', 'event_date' => now()]);
    clearRegistration($event, 'Guest A');

    $this->actingAs($usher)
        ->delete(route('events.registrations.destroy-all', $event), ['confirm_title' => 'Annual Summit'])
        ->assertForbidden();

    expect($event->registrations()->count())->toBe(1);
});
