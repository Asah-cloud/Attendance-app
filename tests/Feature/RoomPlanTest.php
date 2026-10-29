<?php

use App\Models\AccommodationRoom;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\RoomAssignment;
use App\Models\User;
use App\Services\RoomAllocationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
    Storage::fake('local');

    $this->company = Company::create(['name' => 'Plan Co']);
    $this->manager = User::factory()->create(['company_id' => $this->company->id, 'role' => 'manager']);
    $this->manager->assignRole('manager');
    $this->event = Event::create(['company_id' => $this->company->id, 'title' => 'Orientation', 'event_date' => now()->addWeek(), 'accommodation_enabled' => true]);
    unlockAllEventFeatures($this->event);
});

function planRoom(Event $event, string $name, int $beds = 6, string $block = 'Block A', ?string $gender = null): AccommodationRoom
{
    $site = $event->accommodationSites()->firstOrCreate(['name' => 'Main Campus']);
    $floor = $site->blocks()->firstOrCreate(['name' => $block])->floors()->firstOrCreate(['name' => 'Ground']);

    return $floor->rooms()->create(['name' => $name, 'capacity' => $beds, 'gender_restriction' => $gender]);
}

function planList(array $rows): UploadedFile
{
    $lines = ['ID,Name,Gender,Room Group,Category,Phone,Email'];
    foreach ($rows as $i => [$name, $gender, $phone]) {
        $lines[] = ($i + 1).",{$name},{$gender},,Pastor,{$phone},";
    }

    return UploadedFile::fake()->createWithContent('plan.csv', implode("\n", $lines));
}

function sevenMen(): UploadedFile
{
    return planList(array_map(fn ($i) => ["Man {$i}", 'Male', '02000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)], range(1, 7)));
}

it('previews a plan of up to three per room without saving anything', function () {
    foreach (['R1', 'R2', 'R3'] as $room) {
        planRoom($this->event, $room);
    }
    $registered = Participant::create(['company_id' => $this->company->id, 'name' => 'Man 1', 'gender' => 'Male', 'phone' => '200000001']);
    $this->event->registrations()->create(['participant_id' => $registered->id, 'status' => 'confirmed']);
    Participant::create(['company_id' => $this->company->id, 'name' => 'Man 2', 'gender' => 'Male', 'phone' => '200000002']);

    $this->actingAs($this->manager)
        ->post(route('events.accommodation.plan.upload', $this->event), ['file' => sevenMen(), 'per_room' => 3])
        ->assertRedirect(route('events.accommodation.plan.show', $this->event));

    $this->get(route('events.accommodation.plan.show', $this->event))->assertOk()
        ->assertSee('Room plan preview')
        ->assertSeeInOrder(['On the list', '7', 'Will get a room', '7', 'Not registered yet', '6'])
        ->assertSee('Registered for this event')
        ->assertSee('In the system - will be registered')
        ->assertSee('New person - will be added and registered')
        ->assertSee('3 of 6 beds used after this plan');

    $review = session('room-plan.'.$this->event->id);
    expect(collect($review['preview']['rooms'])->map(fn ($room) => count($room['people']))->all())->toBe([3, 3, 1])
        ->and(RoomAssignment::count())->toBe(0)
        ->and(Participant::count())->toBe(2)
        ->and($this->event->registrations()->count())->toBe(1);
});

it('saves the plan, registers everyone listed, and leaves spare beds open for later', function () {
    [$r1, $r2, $r3] = [planRoom($this->event, 'R1'), planRoom($this->event, 'R2'), planRoom($this->event, 'R3')];

    $this->actingAs($this->manager)->post(route('events.accommodation.plan.upload', $this->event), ['file' => sevenMen(), 'per_room' => 3]);
    $this->post(route('events.accommodation.plan.confirm', $this->event))
        ->assertRedirect(route('events.accommodation.index', $this->event))
        ->assertSessionHas('success', 'Room plan saved: 7 placed. 7 people newly registered for this event.');

    expect([$r1->assignments()->count(), $r2->assignments()->count(), $r3->assignments()->count()])->toBe([3, 3, 1])
        ->and(RoomAssignment::where('method', 'plan')->count())->toBe(7)
        ->and($this->event->registrations()->where('status', 'confirmed')->where('accommodation_required', true)->count())->toBe(7)
        ->and(Storage::disk('local')->allFiles('room-plans'))->toBe([]);

    // No cap: a later automatic run can fill the spare beds.
    $late = Participant::create(['company_id' => $this->company->id, 'name' => 'Late Arrival', 'gender' => 'Male']);
    $this->event->registrations()->create(['participant_id' => $late->id, 'status' => 'confirmed', 'accommodation_required' => true]);
    expect(app(RoomAllocationService::class)->commit($this->event, null))->toBe(['assigned' => 1, 'unallocated' => 0]);
});

it('never mixes men and women in a room', function () {
    $a = planRoom($this->event, 'A', 6);
    $b = planRoom($this->event, 'B', 6);
    $list = planList([['Kofi', 'Male', '0201111111'], ['Ama', 'Female', '0202222222'], ['Yaw', 'Male', '0203333333'], ['Esi', 'Female', '0204444444']]);

    $this->actingAs($this->manager)->post(route('events.accommodation.plan.upload', $this->event), ['file' => $list, 'per_room' => 3]);
    $this->post(route('events.accommodation.plan.confirm', $this->event));

    foreach ([$a, $b] as $room) {
        $genders = $room->assignments()->with('registration.participant')->get()->pluck('registration.participant.gender')->unique()->values()->all();
        expect($genders)->toHaveCount(1);
    }
    expect(RoomAssignment::count())->toBe(4);
});

it('keeps to the chosen building and lists who could not be placed', function () {
    planRoom($this->event, 'A1', 6, 'Block A');
    $b1 = planRoom($this->event, 'B1', 6, 'Block B');
    $blockB = $b1->floor->block;

    $this->actingAs($this->manager)->post(route('events.accommodation.plan.upload', $this->event), ['file' => sevenMen(), 'per_room' => 3, 'block_id' => $blockB->id]);
    $preview = session('room-plan.'.$this->event->id)['preview'];
    expect(collect($preview['rooms'])->pluck('label')->unique()->all())->toHaveCount(1)
        ->and(collect($preview['rooms'])->sum(fn ($room) => count($room['people'])))->toBe(6)
        ->and($preview['unallocated'])->toHaveCount(1)
        ->and($preview['unallocated'][0]['reason'])->toBe('No free compatible room in the chosen location.');
    $this->get(route('events.accommodation.plan.show', $this->event))->assertSee('Block B');

    $this->post(route('events.accommodation.plan.confirm', $this->event))
        ->assertSessionHas('success', 'Room plan saved: 6 placed, 1 could not be placed. 7 people newly registered for this event.');
    expect($b1->assignments()->count())->toBe(6);
});

it('leaves people who already have a room where they are', function () {
    $room = planRoom($this->event, 'R1', 6);
    $other = planRoom($this->event, 'R2', 6);
    $person = Participant::create(['company_id' => $this->company->id, 'name' => 'Man 1', 'gender' => 'Male', 'phone' => '200000001']);
    $registration = $this->event->registrations()->create(['participant_id' => $person->id, 'status' => 'confirmed']);
    RoomAssignment::create(['event_registration_id' => $registration->id, 'accommodation_room_id' => $other->id, 'status' => 'assigned', 'method' => 'manual']);

    $this->actingAs($this->manager)->post(route('events.accommodation.plan.upload', $this->event), ['file' => sevenMen(), 'per_room' => 3]);
    expect(session('room-plan.'.$this->event->id)['preview']['already'][0]['name'])->toBe('Man 1');

    $this->post(route('events.accommodation.plan.confirm', $this->event))
        ->assertSessionHas('success', 'Room plan saved: 6 placed, 1 already had a room. 6 people newly registered for this event.');
    expect($registration->fresh()->roomAssignment->accommodation_room_id)->toBe($other->id);
});

it('asks about rows that match someone of another name and applies the choice', function () {
    planRoom($this->event, 'R1', 6);
    $existing = Participant::create(['company_id' => $this->company->id, 'name' => 'Issac Kwabia', 'gender' => 'Male', 'phone' => '545829491']);

    $this->actingAs($this->manager)->post(route('events.accommodation.plan.upload', $this->event), ['file' => planList([['Isaac Kwabia', 'Male', '0545829491']]), 'per_room' => 3]);
    $this->get(route('events.accommodation.plan.show', $this->event))->assertSee('Check 1 row')->assertSee('Issac Kwabia');

    $this->post(route('events.accommodation.plan.refresh', $this->event), ['decisions' => [2 => 'update']])->assertRedirect(route('events.accommodation.plan.show', $this->event));
    $this->get(route('events.accommodation.plan.show', $this->event))->assertSee('name="decisions[2]" value="update" checked', false);
    $this->post(route('events.accommodation.plan.confirm', $this->event), ['decisions' => [2 => 'update']]);

    expect($existing->fresh()->name)->toBe('Isaac Kwabia')
        ->and(Participant::count())->toBe(1)
        ->and(EventRegistration::where('participant_id', $existing->id)->first()->roomAssignment)->not->toBeNull();
});

it('cancels a plan without changing anything and keeps other companies out', function () {
    planRoom($this->event, 'R1', 6);
    $this->actingAs($this->manager)->post(route('events.accommodation.plan.upload', $this->event), ['file' => sevenMen(), 'per_room' => 3]);
    $this->post(route('events.accommodation.plan.cancel', $this->event))->assertSessionHas('success', 'Room plan cancelled. Nothing was changed.');
    expect(Participant::count())->toBe(0)->and(Storage::disk('local')->allFiles('room-plans'))->toBe([]);

    $outsider = User::factory()->create(['company_id' => Company::create(['name' => 'Other'])->id, 'role' => 'manager']);
    $outsider->assignRole('manager');
    $this->actingAs($outsider)->post(route('events.accommodation.plan.upload', $this->event), ['file' => sevenMen(), 'per_room' => 3])->assertForbidden();
});

function floorRoom(Event $event, string $block, string $floor, string $name, int $beds = 3): AccommodationRoom
{
    $site = $event->accommodationSites()->firstOrCreate(['name' => 'Main Campus']);

    return $site->blocks()->firstOrCreate(['name' => $block])->floors()->firstOrCreate(['name' => $floor])
        ->rooms()->create(['name' => $name, 'capacity' => $beds]);
}

function roomsOf(Event $event): array
{
    return collect(session('room-plan.'.$event->id)['preview']['rooms'])
        ->map(fn ($room) => Str::afterLast($room['label'], ' / ').':'.count($room['people']))->all();
}

it('fills only the chosen floor, lowest room number first', function () {
    floorRoom($this->event, 'New Block', 'First Floor', 'NB-101');
    foreach (['NB-210', 'NB-201', 'NB-202'] as $name) {
        $floor = floorRoom($this->event, 'New Block', 'Second Floor', $name)->floor;
    }

    $this->actingAs($this->manager)->post(route('events.accommodation.plan.upload', $this->event), ['file' => sevenMen(), 'per_room' => 3, 'floor_id' => $floor->id]);

    expect(roomsOf($this->event))->toBe(['NB-201:3', 'NB-202:3', 'NB-210:1']);
    $this->get(route('events.accommodation.plan.show', $this->event))->assertSee('New Block · Second Floor (Main Campus)');
});

it('fills only the typed or ticked rooms', function () {
    foreach (['NB-101', 'NB-102', 'NB-103', 'NB-104', 'OB-101'] as $name) {
        $rooms[$name] = floorRoom($this->event, str_starts_with($name, 'NB') ? 'New Block' : 'Old Block', 'First Floor', $name, 2);
    }
    $block = $rooms['NB-101']->floor->block;

    // A range by number, within the chosen building, plus a ticked room.
    $this->actingAs($this->manager)->post(route('events.accommodation.plan.upload', $this->event), [
        'file' => sevenMen(), 'per_room' => 2, 'block_id' => $block->id, 'rooms' => '102-103', 'room_ids' => [$rooms['NB-104']->id],
    ]);
    expect(roomsOf($this->event))->toBe(['NB-102:2', 'NB-103:2', 'NB-104:2']);
    $this->get(route('events.accommodation.plan.show', $this->event))->assertOk()
        ->assertSee('3 chosen rooms (NB-102, NB-103, NB-104)')
        // 7 people, 6 free beds in those rooms.
        ->assertSee('data-short-of-beds', false)
        ->assertSee('the chosen rooms have 6 free beds, but 7 people on the list need a room');

    // A range with letters keeps to rooms with those letters, across buildings.
    $this->post(route('events.accommodation.plan.upload', $this->event), ['file' => sevenMen(), 'per_room' => 2, 'rooms' => 'OB-101 to OB-105']);
    expect(roomsOf($this->event))->toBe(['OB-101:2']);

    // A part that matches nothing is sent back, nothing is stored.
    $this->post(route('events.accommodation.plan.upload', $this->event), ['file' => sevenMen(), 'per_room' => 2, 'block_id' => $block->id, 'rooms' => '101, 999'])
        ->assertSessionHasErrors(['rooms' => 'No room in the chosen place matches: 999.']);
    $this->post(route('events.accommodation.plan.upload', $this->event), ['file' => sevenMen(), 'per_room' => 2, 'block_id' => $block->id, 'room_ids' => [$rooms['OB-101']->id]])
        ->assertSessionHasErrors(['rooms' => 'None of the chosen rooms are in the chosen place.']);
});

it('puts people in the room named in the list\'s Room column', function () {
    $named = floorRoom($this->event, 'New Block', 'Third Floor', 'NB-301', 2);
    $full = floorRoom($this->event, 'New Block', 'Third Floor', 'NB-302', 1);
    $sitting = Participant::create(['company_id' => $this->company->id, 'name' => 'Already There', 'gender' => 'Male']);
    RoomAssignment::create(['event_registration_id' => $this->event->registrations()->create(['participant_id' => $sitting->id, 'status' => 'confirmed'])->id, 'accommodation_room_id' => $full->id, 'status' => 'assigned', 'method' => 'manual']);
    $floor = floorRoom($this->event, 'New Block', 'First Floor', 'NB-101', 4)->floor;
    floorRoom($this->event, 'Old Block', 'First Floor', 'OB-301');

    $list = UploadedFile::fake()->createWithContent('plan.csv', implode("\n", [
        'Name,Gender,Phone,Room',
        'Speaker One,Male,0201000001,nb 301',
        'Late Speaker,Male,0201000002,NB-302',
        'Lost Guest,Male,0201000003,NB-999',
        'Unclear Guest,Male,0201000004,301',
        'Plain Guest,Male,0201000005,',
    ]));
    $this->actingAs($this->manager)->post(route('events.accommodation.plan.upload', $this->event), ['file' => $list, 'per_room' => 3, 'floor_id' => $floor->id]);

    // The named room is used although it is on another floor; the rest go to the chosen floor.
    expect(roomsOf($this->event))->toBe(['NB-301:1', 'NB-101:1'])
        ->and(collect(session('room-plan.'.$this->event->id)['preview']['unallocated'])->pluck('reason', 'name')->all())->toBe([
            'Late Speaker' => 'Room NB-302 on the list is full.',
            'Lost Guest' => 'Room NB-999 on the list was not found, or is closed.',
            'Unclear Guest' => 'More than one room matches 301: write it as on the room list.',
        ]);

    $this->post(route('events.accommodation.plan.confirm', $this->event))->assertSessionHas('success');
    expect($named->assignments()->sole()->allocation_reason)->toBe('Room plan: room named on the list.');
});
