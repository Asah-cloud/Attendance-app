<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\RoomAssignment;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
    $this->company = Company::create(['name' => 'Board Co']);
    $this->manager = User::factory()->create(['company_id' => $this->company->id, 'role' => 'manager']);
    $this->manager->assignRole('manager');
    $this->event = Event::create(['company_id' => $this->company->id, 'title' => 'Retreat', 'event_date' => now()->addWeek(), 'accommodation_enabled' => true]);
    unlockAllEventFeatures($this->event);
    $site = $this->event->accommodationSites()->create(['name' => 'Main Campus']);
    $menFloor = $site->blocks()->create(['name' => 'Block A', 'gender_restriction' => 'Male'])->floors()->create(['name' => 'Ground']);
    $this->a1 = $menFloor->rooms()->create(['name' => 'A01', 'capacity' => 2]);
    $this->a2 = $menFloor->rooms()->create(['name' => 'A02', 'capacity' => 2]);
    $this->kofi = boardGuest($this->event, 'Kofi Mensah', 'Male');
    $this->ama = boardGuest($this->event, 'Ama Serwaa', 'Female');
});

function boardGuest(Event $event, string $name, string $gender): EventRegistration
{
    $participant = Participant::create(['company_id' => $event->company_id, 'name' => $name, 'gender' => $gender]);

    return $event->registrations()->create(['participant_id' => $participant->id, 'status' => 'confirmed', 'accommodation_required' => true]);
}

function moveOnBoard(EventRegistration $registration, ?int $roomId, bool $confirmed = false)
{
    return test()->actingAs(test()->manager)->postJson(route('events.accommodation.board.move', test()->event), array_filter([
        'registration_id' => $registration->id, 'room_id' => $roomId, 'confirmed' => $confirmed ?: null,
    ], fn ($value) => $value !== null));
}

it('shows rooms with their occupants and the people who still need a room', function () {
    RoomAssignment::create(['event_registration_id' => $this->kofi->id, 'accommodation_room_id' => $this->a1->id, 'status' => 'assigned', 'method' => 'manual']);

    $this->actingAs($this->manager)->get(route('events.accommodation.board', $this->event))->assertOk()
        ->assertSee('data-room-board', false)
        ->assertSeeInOrder(['Needs a room', 'Ama Serwaa', 'A01', '1 / 2 beds', 'Kofi Mensah', 'A02', '0 / 2 beds']);
    $this->get(route('events.accommodation.index', $this->event))->assertSee(route('events.accommodation.board', $this->event), false);
});

it('moves people between rooms and back to needs-a-room, re-arming the room email', function () {
    moveOnBoard($this->kofi, $this->a1->id)->assertOk()->assertJson(['ok' => true]);
    $this->kofi->roomAssignment()->update(['notification_sent_at' => now()]);

    moveOnBoard($this->kofi, $this->a2->id)->assertOk();
    $assignment = $this->kofi->fresh()->roomAssignment;
    expect($assignment->accommodation_room_id)->toBe($this->a2->id)
        ->and($assignment->method)->toBe('manual')
        ->and($assignment->notification_sent_at)->toBeNull();

    moveOnBoard($this->kofi, null)->assertOk();
    expect($this->kofi->fresh()->roomAssignment()->whereIn('status', ['assigned', 'checked_in'])->exists())->toBeFalse();
});

it('asks before putting someone in a room for, or with, the other gender', function () {
    moveOnBoard($this->ama, $this->a1->id)->assertStatus(409)
        ->assertJson(['ok' => false, 'confirm' => 'A01 is set aside for male guests. Put Ama Serwaa there anyway?']);
    expect($this->ama->fresh()->roomAssignment)->toBeNull();

    moveOnBoard($this->ama, $this->a1->id, confirmed: true)->assertOk();
    expect($this->ama->fresh()->roomAssignment->accommodation_room_id)->toBe($this->a1->id);

    $this->a2->floor->block->update(['gender_restriction' => null]);
    moveOnBoard($this->ama, $this->a2->id, confirmed: true)->assertOk();
    moveOnBoard($this->kofi, $this->a2->id)->assertStatus(409)
        ->assertJson(['confirm' => 'A02 already has guests of another gender. Put Kofi Mensah there anyway?']);
});

it('refuses a full room and a checked-in guest', function () {
    $other = boardGuest($this->event, 'Yaw Owusu', 'Male');
    moveOnBoard($this->kofi, $this->a1->id);
    moveOnBoard($other, $this->a1->id);
    $third = boardGuest($this->event, 'Kojo Asante', 'Male');

    moveOnBoard($third, $this->a1->id)->assertStatus(422)->assertJson(['ok' => false, 'message' => 'That room is already full.']);

    $this->kofi->roomAssignment()->update(['status' => 'checked_in']);
    moveOnBoard($this->kofi->fresh(), $this->a2->id)->assertStatus(422)->assertJson(['message' => 'Checked-in attendees cannot be moved.']);
});

it('keeps other companies off the board and away from other events\' rooms', function () {
    $outsider = User::factory()->create(['company_id' => Company::create(['name' => 'Other'])->id, 'role' => 'manager']);
    $outsider->assignRole('manager');
    $this->actingAs($outsider)->get(route('events.accommodation.board', $this->event))->assertForbidden();
    $this->postJson(route('events.accommodation.board.move', $this->event), ['registration_id' => $this->kofi->id, 'room_id' => $this->a1->id])->assertForbidden();

    $elsewhere = Event::create(['company_id' => $this->company->id, 'title' => 'Other Event', 'event_date' => now()]);
    $foreignRoom = $elsewhere->accommodationSites()->create(['name' => 'X'])->blocks()->create(['name' => 'X'])->floors()->create(['name' => 'X'])->rooms()->create(['name' => 'X1', 'capacity' => 4]);
    moveOnBoard($this->kofi, $foreignRoom->id)->assertNotFound();
});
