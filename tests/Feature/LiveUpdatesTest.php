<?php

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Event;
use App\Models\Participant;
use App\Models\RoomAssignment;
use App\Models\User;
use App\Services\LiveVersion;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
    $this->company = Company::create(['name' => 'Live Co']);
    $this->manager = User::factory()->create(['company_id' => $this->company->id, 'role' => 'manager']);
    $this->manager->assignRole('manager');
    $this->event = Event::create(['company_id' => $this->company->id, 'title' => 'Live Event', 'event_date' => now()->addWeek(), 'accommodation_enabled' => true]);
    unlockAllEventFeatures($this->event);
    $this->person = Participant::create(['company_id' => $this->company->id, 'name' => 'Ama Owusu', 'gender' => 'Female', 'category' => 'Guest']);
    $this->registration = $this->event->registrations()->create(['participant_id' => $this->person->id, 'status' => 'confirmed']);
    $this->live = app(LiveVersion::class);
    // Write what setting up changed, as the end of a request would.
    $this->live->flush();
});

function liveVersion($test): string
{
    return $test->actingAs($test->manager)->getJson(route('live.version', ['event' => $test->event->id]))->assertOk()->json('version');
}

it('gives open pages a marker to watch and an area to redraw', function () {
    $version = liveVersion($this);

    $this->get(route('events.registrations.index', $this->event))->assertOk()
        ->assertSee('<meta name="live-version" content="'.route('live.version', ['event' => $this->event->id]).'" data-version="'.$version.'">', false)
        ->assertSee('data-live-root', false);
    $this->get(route('events.accommodation.board', $this->event))->assertOk()
        ->assertSee('name="live-version"', false)->assertSee('data-live-root', false);
    $this->get(route('events.badges', $this->event))->assertOk()
        ->assertSee('name="live-version"', false)->assertSee('id="badge-attendees"', false);
});

it('moves the marker when someone\'s name or gender is edited', function () {
    $before = liveVersion($this);

    $this->patch(route('events.registrations.participant.update', [$this->event, $this->registration]), [
        'name' => 'Ama Serwaa Owusu', 'gender' => 'Female', 'category' => 'Guest',
    ])->assertSessionHas('success');

    expect(liveVersion($this))->not->toBe($before);

    // A change made outside a page (a merge, a script) moves it too.
    $before = liveVersion($this);
    $this->person->update(['gender' => 'Male']);
    $this->live->flush();
    expect(liveVersion($this))->not->toBe($before);
});

it('moves the marker for room moves and for changes made in bulk', function () {
    $room = $this->event->accommodationSites()->create(['name' => 'Campus'])->blocks()->create(['name' => 'A'])
        ->floors()->create(['name' => 'Ground'])->rooms()->create(['name' => 'A1', 'capacity' => 2]);
    $before = liveVersion($this);
    $this->postJson(route('events.accommodation.board.move', $this->event), ['registration_id' => $this->registration->id, 'room_id' => $room->id])->assertOk();
    expect(liveVersion($this))->not->toBe($before)
        ->and(RoomAssignment::count())->toBe(1);

    // Marking everyone as needing a room is one bulk query, with no model events.
    $before = liveVersion($this);
    $this->post(route('events.accommodation.mark-all-required', $this->event))->assertRedirect();
    expect(liveVersion($this))->not->toBe($before);
});

it('leaves the marker alone for check-ins', function () {
    $before = liveVersion($this);
    Attendance::create(['event_id' => $this->event->id, 'participant_id' => $this->person->id, 'day' => 1, 'status' => 'present']);
    $this->live->flush();

    expect(liveVersion($this))->toBe($before);
});

it('only tells people of the company that owns the event', function () {
    $stranger = User::factory()->create(['company_id' => Company::create(['name' => 'Other'])->id, 'role' => 'manager']);
    $stranger->assignRole('manager');

    $this->actingAs($stranger)->getJson(route('live.version', ['event' => $this->event->id]))->assertForbidden();
    $this->actingAs($stranger)->getJson(route('live.version'))->assertOk()->assertJsonStructure(['version']);
});
