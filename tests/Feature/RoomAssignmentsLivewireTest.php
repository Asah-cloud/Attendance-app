<?php

use App\Livewire\RoomAssignments;
use App\Models\AccommodationRoom;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\RoomAssignment;
use App\Models\User;
use App\Notifications\RoomAssigned;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Notification::fake();
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function liveRoomManager(Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('manager');

    return $user;
}

function liveRoomRegistration(Event $event, string $name, string $gender = 'Male', string $category = 'General', bool $accessible = false): EventRegistration
{
    $participant = Participant::create(['company_id' => $event->company_id, 'name' => $name, 'email' => str($name)->slug().uniqid().'@example.com', 'gender' => $gender, 'category' => $category]);

    return EventRegistration::create(['event_id' => $event->id, 'participant_id' => $participant->id, 'status' => EventRegistration::STATUS_CONFIRMED, 'accommodation_required' => true, 'accessibility_required' => $accessible]);
}

function liveRoom(Event $event, string $name, int $capacity = 1, array $attributes = []): AccommodationRoom
{
    $site = $event->accommodationSites()->firstOrCreate(['name' => $attributes['site'] ?? 'Main Campus']);
    $block = $site->blocks()->firstOrCreate(['name' => $attributes['block'] ?? 'Block A'], ['gender_restriction' => $attributes['block_gender'] ?? null]);
    $floor = $block->floors()->firstOrCreate(['name' => $attributes['floor'] ?? 'Ground'], ['is_accessible' => $attributes['floor_accessible'] ?? false]);

    return $floor->rooms()->create(['name' => $name, 'capacity' => $capacity, 'gender_restriction' => $attributes['gender'] ?? null, 'category_restriction' => $attributes['category'] ?? null, 'is_accessible' => $attributes['accessible'] ?? false]);
}

it('assigns a room through the Livewire component without a page navigation', function () {
    $company = Company::create(['name' => 'Acme']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now(), 'accommodation_enabled' => true, 'accommodation_published' => true]);
    $manager = liveRoomManager($company);
    $room = liveRoom($event, 'A01', 2);
    $registration = liveRoomRegistration($event, 'Jane Doe');

    $this->actingAs($manager);
    Livewire::test(RoomAssignments::class, ['event' => $event])
        ->assertSee('Jane Doe')
        ->assertSee($room->name)
        ->set("selectedRoom.{$registration->id}", $room->id)
        ->call('assign', $registration->id)
        ->assertDispatched('notify', message: 'Room assigned.', type: 'success');

    $registration->refresh();
    expect($registration->roomAssignment->accommodation_room_id)->toBe($room->id)
        ->and($registration->roomAssignment->method)->toBe('manual');
    Notification::assertSentTo($registration->participant, RoomAssigned::class);
});

it('searches, filters to unassigned, and paginates within the component', function () {
    $company = Company::create(['name' => 'Acme']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now(), 'accommodation_enabled' => true]);
    $manager = liveRoomManager($company);
    $room = liveRoom($event, 'A01', 5);
    $assigned = liveRoomRegistration($event, 'Priya Roomed');
    $assigned->roomAssignment()->create(['accommodation_room_id' => $room->id, 'status' => 'assigned', 'method' => 'manual']);
    liveRoomRegistration($event, 'Kojo Waiting');

    $this->actingAs($manager);
    Livewire::test(RoomAssignments::class, ['event' => $event])
        ->assertSee('Priya Roomed')
        ->assertSee('Kojo Waiting')
        ->set('unassignedOnly', true)
        ->assertDontSee('Priya Roomed')
        ->assertSee('Kojo Waiting')
        ->set('unassignedOnly', false)
        ->set('search', 'priya')
        ->assertSee('Priya Roomed')
        ->assertDontSee('Kojo Waiting');
});

it('flags a room that does not match the attendee\'s gender as differing', function () {
    $company = Company::create(['name' => 'Acme']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now(), 'accommodation_enabled' => true]);
    $manager = liveRoomManager($company);
    liveRoom($event, 'F01', 2, ['gender' => 'Female']);
    liveRoomRegistration($event, 'Male Guest', 'Male');

    $this->actingAs($manager);
    Livewire::test(RoomAssignments::class, ['event' => $event])
        ->assertSee('differs');
});

it('lets a manager remove an assignment through the component, but not once checked in', function () {
    $company = Company::create(['name' => 'Acme']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now(), 'accommodation_enabled' => true]);
    $manager = liveRoomManager($company);
    $room = liveRoom($event, 'A01', 2);
    $registration = liveRoomRegistration($event, 'Jane Doe');
    $registration->roomAssignment()->create(['accommodation_room_id' => $room->id, 'status' => 'assigned', 'method' => 'manual']);

    $this->actingAs($manager);
    Livewire::test(RoomAssignments::class, ['event' => $event])
        ->call('removeAssignment', $registration->id)
        ->assertDispatched('notify', message: 'Room assignment removed.', type: 'success');

    expect($registration->fresh()->roomAssignment)->toBeNull();

    $registration->roomAssignment()->create(['accommodation_room_id' => $room->id, 'status' => 'checked_in']);
    Livewire::test(RoomAssignments::class, ['event' => $event])
        ->call('removeAssignment', $registration->id)
        ->assertDispatched('notify', type: 'error');

    expect($registration->fresh()->roomAssignment)->not->toBeNull();
});

it('prevents a manager from another company reaching the room assignments component', function () {
    $company = Company::create(['name' => 'Acme']);
    $otherCompany = Company::create(['name' => 'Other']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);
    $outsider = liveRoomManager($otherCompany);

    $this->actingAs($outsider);
    Livewire::test(RoomAssignments::class, ['event' => $event])->assertForbidden();
});

it('rejects assigning a full room', function () {
    $company = Company::create(['name' => 'Acme']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now(), 'accommodation_enabled' => true]);
    $manager = liveRoomManager($company);
    $room = liveRoom($event, 'A01', 1);
    $existing = liveRoomRegistration($event, 'Existing Guest');
    $existing->roomAssignment()->create(['accommodation_room_id' => $room->id, 'status' => 'assigned', 'method' => 'manual']);
    $newcomer = liveRoomRegistration($event, 'New Guest');

    $this->actingAs($manager);
    Livewire::test(RoomAssignments::class, ['event' => $event])
        ->set("selectedRoom.{$newcomer->id}", $room->id)
        ->call('assign', $newcomer->id)
        ->assertDispatched('notify', message: 'That room is already full.', type: 'error');

    expect(RoomAssignment::where('event_registration_id', $newcomer->id)->exists())->toBeFalse();
});
