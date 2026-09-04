<?php

use App\Imports\UsersImport;
use App\Models\AccommodationRoom;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\EventRegistrationSubmitted;
use App\Notifications\RegistrationLifecycleNotification;
use App\Notifications\RoomAssigned;
use App\Notifications\RoomSelectionInvite;
use App\Services\RegistrationLifecycleService;
use App\Services\RoomAllocationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

function accommodationManager(Company $company): User
{
    Role::findOrCreate('manager');
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('manager');

    return $user;
}

function accommodationRegistration(Event $event, string $name, string $gender = 'Male', string $category = 'General', bool $accessible = false): EventRegistration
{
    $participant = Participant::create(['company_id' => $event->company_id, 'name' => $name, 'email' => str($name)->slug().uniqid().'@example.com', 'gender' => $gender, 'category' => $category]);

    return EventRegistration::create(['event_id' => $event->id, 'participant_id' => $participant->id, 'status' => EventRegistration::STATUS_CONFIRMED, 'accommodation_required' => true, 'accessibility_required' => $accessible]);
}

function accommodationRoom(Event $event, string $name, int $capacity = 1, array $attributes = []): AccommodationRoom
{
    $site = $event->accommodationSites()->firstOrCreate(['name' => 'Main Campus']);
    $block = $site->blocks()->firstOrCreate(['name' => $attributes['block'] ?? 'Block A'], ['gender_restriction' => $attributes['block_gender'] ?? null]);
    $floor = $block->floors()->firstOrCreate(['name' => $attributes['floor'] ?? 'Ground'], ['is_accessible' => $attributes['floor_accessible'] ?? false]);

    return $floor->rooms()->create(['name' => $name, 'capacity' => $capacity, 'gender_restriction' => $attributes['gender'] ?? null, 'category_restriction' => $attributes['category'] ?? null, 'is_accessible' => $attributes['accessible'] ?? false]);
}

it('allocates confirmed attendees without exceeding capacity', function () {
    $company = Company::create(['name' => 'Acme']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now(), 'accommodation_enabled' => true]);
    $room = accommodationRoom($event, 'A01', 2);
    accommodationRegistration($event, 'First Guest');
    accommodationRegistration($event, 'Second Guest');
    accommodationRegistration($event, 'Third Guest');

    $result = app(RoomAllocationService::class)->commit($event, null);

    expect($result)->toBe(['assigned' => 2, 'unallocated' => 1]);
    expect($room->assignments()->count())->toBe(2);
});

it('honours gender category and accessibility restrictions', function () {
    $company = Company::create(['name' => 'Acme']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now(), 'accommodation_enabled' => true]);
    $maleRoom = accommodationRoom($event, 'M1', 4, ['gender' => 'Male']);
    $accessibleRoom = accommodationRoom($event, 'F1', 1, ['block' => 'Block B', 'gender' => 'Female', 'accessible' => true]);
    $male = accommodationRegistration($event, 'Male Guest', 'Male');
    $female = accommodationRegistration($event, 'Female Guest', 'Female', 'General', true);

    app(RoomAllocationService::class)->commit($event, null);

    expect($male->fresh()->roomAssignment->accommodation_room_id)->toBe($maleRoom->id);
    expect($female->fresh()->roomAssignment->accommodation_room_id)->toBe($accessibleRoom->id);
});

it('lets a manager build inventory and blocks another company manager', function () {
    $company = Company::create(['name' => 'Acme']);
    $other = Company::create(['name' => 'Other']);
    $manager = accommodationManager($company);
    $outsider = accommodationManager($other);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);

    $this->actingAs($manager)->post(route('events.accommodation.sites.store', $event), ['name' => 'Campus'])->assertRedirect();
    $this->assertDatabaseHas('accommodation_sites', ['event_id' => $event->id, 'name' => 'Campus']);
    $this->actingAs($outsider)->get(route('events.accommodation.index', $event))->assertForbidden();
});

it('automatically assigns accommodation requested during public registration', function () {
    $company = Company::create(['name' => 'Acme']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'slug' => 'summit', 'event_date' => now()->addDay(), 'registration_enabled' => true, 'accommodation_enabled' => true]);
    accommodationRoom($event, 'A01', 1, ['gender' => 'Female']);
    $event->ensureSystemRegistrationFields();

    $this->post(route('events.register.store', $event), [
        'name' => 'Ada Guest', 'email' => 'ada@example.com', 'phone' => '0201234567', 'gender' => 'Female', 'category' => 'General',
        'accommodation_required' => 1, 'accessibility_required' => 0, 'consent' => 1,
    ])->assertRedirect();

    $registration = EventRegistration::whereHas('participant', fn ($query) => $query->where('email', 'ada@example.com'))->firstOrFail();
    expect($registration->accommodation_required)->toBeTrue()->and($registration->roomAssignment)->not->toBeNull();
});

it('reveals a published assignment in an arrival scanner response', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = accommodationManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now(), 'has_arrival_session' => true, 'arrival_date' => now(), 'accommodation_enabled' => true, 'accommodation_published' => true]);
    $registration = accommodationRegistration($event, 'Arrival Guest');
    $room = accommodationRoom($event, 'A01');
    $registration->roomAssignment()->create(['accommodation_room_id' => $room->id, 'status' => 'assigned', 'method' => 'automatic']);

    $this->actingAs($manager)->postJson(route('events.arrival.scanner.check-in', $event), ['registration_code' => $registration->registration_code])
        ->assertOk()->assertJsonPath('room', 'Main Campus / Block A / Ground / A01')->assertJsonFragment(['successful' => true]);
});

it('checks an attendee into and out of accommodation while preserving history', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = accommodationManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now(), 'accommodation_enabled' => true]);
    $registration = accommodationRegistration($event, 'Room Guest');
    $room = accommodationRoom($event, 'A01');
    $assignment = $registration->roomAssignment()->create(['accommodation_room_id' => $room->id, 'status' => 'assigned', 'method' => 'automatic']);

    $this->actingAs($manager)->post(route('events.accommodation.check-in', [$event, $registration]))->assertRedirect();
    expect($assignment->fresh()->status)->toBe('checked_in')->and($assignment->fresh()->is_locked)->toBeTrue()->and($assignment->fresh()->checked_in_by)->toBe($manager->id);

    $this->actingAs($manager)->post(route('events.accommodation.check-out', [$event, $registration]))->assertRedirect();
    expect($assignment->fresh()->status)->toBe('checked_out')->and($assignment->fresh()->checked_out_by)->toBe($manager->id);
    $this->assertDatabaseHas('room_assignments', ['id' => $assignment->id]);
});

it('notifies assigned attendees when assignments are published', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Acme']);
    $manager = accommodationManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now(), 'accommodation_enabled' => true]);
    $registration = accommodationRegistration($event, 'Notify Guest');
    $room = accommodationRoom($event, 'A01');
    $assignment = $registration->roomAssignment()->create(['accommodation_room_id' => $room->id, 'status' => 'assigned', 'method' => 'automatic']);

    $this->actingAs($manager)->patch(route('events.accommodation.settings', $event), ['accommodation_enabled' => 1, 'accommodation_published' => 1])->assertRedirect();

    Notification::assertSentTo($registration->participant, RoomAssigned::class);
    expect($assignment->fresh()->notification_sent_at)->not->toBeNull();
});

it('bulk creates and imports rooms without duplicating inventory', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = accommodationManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);
    $seed = accommodationRoom($event, 'Existing');
    $floor = $seed->floor;

    $this->actingAs($manager)->post(route('events.accommodation.rooms.bulk', [$event, $floor]), ['prefix' => 'A', 'start' => 1, 'end' => 3, 'capacity' => 4])->assertRedirect();
    expect($floor->rooms()->whereIn('name', ['A1', 'A2', 'A3'])->count())->toBe(3);

    $csv = "location,building,floor,room,capacity,gender,category,accessible\nAnnex,Block B,First,B101,2,Female,VIP,true\n";
    $this->actingAs($manager)->post(route('events.accommodation.import', $event), ['file' => UploadedFile::fake()->createWithContent('rooms.csv', $csv)])->assertRedirect();
    $this->assertDatabaseHas('accommodation_rooms', ['name' => 'B101', 'capacity' => 2, 'gender_restriction' => 'Female', 'category_restriction' => 'VIP', 'is_accessible' => true]);
    $this->assertDatabaseHas('accommodation_sites', ['name' => 'Annex']);
});

it('lets a manager rename a room and rejects a duplicate name on the same floor', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = accommodationManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);
    $room = accommodationRoom($event, 'A01', 2);
    $sibling = $room->floor->rooms()->create(['name' => 'A02', 'capacity' => 2]);

    $this->actingAs($manager)->patch(route('events.accommodation.rooms.update', [$event, $room]), [
        'name' => 'Suite 1', 'capacity' => 3, 'status' => 'active',
    ])->assertRedirect();
    expect($room->fresh()->name)->toBe('Suite 1')->and($room->fresh()->capacity)->toBe(3);

    $this->actingAs($manager)->from(route('events.accommodation.index', $event))
        ->patch(route('events.accommodation.rooms.update', [$event, $room]), ['name' => 'A02', 'capacity' => 3, 'status' => 'active'])
        ->assertSessionHasErrors('name');
    expect($room->fresh()->name)->toBe('Suite 1');
    expect($sibling->fresh()->name)->toBe('A02');
});

it('matches rooms despite loose gender spellings', function () {
    $company = Company::create(['name' => 'Acme']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now(), 'accommodation_enabled' => true]);
    $maleRoom = accommodationRoom($event, 'M1', 1, ['gender' => 'Male']);
    $femaleRoom = accommodationRoom($event, 'F1', 1, ['block' => 'Block B', 'gender' => 'Female']);
    $man = accommodationRegistration($event, 'Loose Man', 'M');
    $woman = accommodationRegistration($event, 'Loose Woman', 'female');

    app(RoomAllocationService::class)->commit($event, null);

    expect($man->fresh()->roomAssignment->accommodation_room_id)->toBe($maleRoom->id)
        ->and($woman->fresh()->roomAssignment->accommodation_room_id)->toBe($femaleRoom->id);
});

it('places one attendee at a time on confirmation without overbooking', function () {
    $company = Company::create(['name' => 'Acme']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'slug' => 'summit-one', 'event_date' => now()->addDay(), 'registration_enabled' => true, 'accommodation_enabled' => true]);
    accommodationRoom($event, 'Solo', 1);
    $event->ensureSystemRegistrationFields();

    foreach (['first' => '0201000011', 'second' => '0201000022'] as $who => $phone) {
        $this->post(route('events.register.store', $event), [
            'name' => "Guest {$who}", 'email' => "{$who}@example.com", 'phone' => $phone,
            'gender' => 'Male', 'category' => 'General', 'accommodation_required' => 1, 'consent' => 1,
        ])->assertRedirect();
    }

    expect($event->registrations()->whereHas('roomAssignment')->count())->toBe(1);
});

it('imports a CSV that starts with a UTF-8 BOM', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = accommodationManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);
    $csv = "\u{FEFF}location,building,floor,room,capacity\nMain,Block A,Ground,G1,3\n";

    $this->actingAs($manager)->post(route('events.accommodation.import', $event), ['file' => UploadedFile::fake()->createWithContent('rooms.csv', $csv)])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('accommodation_rooms', ['name' => 'G1', 'capacity' => 3]);
});

it('holds reserved rooms back from auto-allocation but allows manual assignment', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Acme']);
    $manager = accommodationManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now(), 'accommodation_enabled' => true]);
    $vip = accommodationRegistration($event, 'VIP Guest');
    $general = accommodationRegistration($event, 'General Guest');
    $reserved = accommodationRoom($event, 'Presidential Suite', 1);
    $reserved->update(['status' => 'reserved']);
    $standard = accommodationRoom($event, 'Std 1', 1, ['floor' => 'First']);

    app(RoomAllocationService::class)->commit($event, null);
    expect($reserved->activeAssignments()->count())->toBe(0);
    expect($standard->activeAssignments()->count())->toBe(1);

    $this->actingAs($manager)->from(route('events.accommodation.index', $event))
        ->put(route('events.accommodation.assignments.update', [$event, $vip]), ['room_id' => $reserved->id, 'is_locked' => 1])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($vip->fresh()->roomAssignment->accommodation_room_id)->toBe($reserved->id)
        ->and($vip->fresh()->roomAssignment->is_locked)->toBeTrue();

    $reserved->activeAssignments()->delete();
    $reserved->update(['status' => 'closed']);
    $this->actingAs($manager)
        ->put(route('events.accommodation.assignments.update', [$event, $general]), ['room_id' => $reserved->id])
        ->assertStatus(422);
    expect($general->fresh()->roomAssignment?->accommodation_room_id)->not->toBe($reserved->id);
});

it('exports rooming lists and protects inventory with assignment history', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = accommodationManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);
    $registration = accommodationRegistration($event, 'Report Guest');
    $room = accommodationRoom($event, 'A01');
    $registration->roomAssignment()->create(['accommodation_room_id' => $room->id, 'status' => 'assigned', 'method' => 'automatic']);

    $csv = $this->actingAs($manager)->get(route('events.accommodation.report.csv', $event))->assertOk();
    expect($csv->streamedContent())->toContain('Report Guest');
    $this->actingAs($manager)->get(route('events.accommodation.report.pdf', $event))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($manager)->delete(route('events.accommodation.inventory.destroy', [$event, 'room', $room->id]))->assertStatus(422);
    $this->assertDatabaseHas('accommodation_rooms', ['id' => $room->id]);
});

it('lets a confirmed attendee choose their own room before the cutoff', function () {
    $company = Company::create(['name' => 'Acme']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()->addWeek(), 'accommodation_enabled' => true, 'accommodation_self_select_closes_at' => now()->addDays(2)]);
    $registration = accommodationRegistration($event, 'Chooser');
    $upstairs = accommodationRoom($event, 'Room 210', 2, ['floor' => 'Second']);
    accommodationRoom($event, 'Room 011', 2);

    $this->get(route('registrations.room.select', $registration->registration_code))->assertOk()->assertSee('Room 210');

    $this->post(route('registrations.room.claim', $registration->registration_code), ['room_id' => $upstairs->id])
        ->assertRedirect(route('registrations.room.select', $registration->registration_code));

    expect($registration->fresh()->roomAssignment->accommodation_room_id)->toBe($upstairs->id)
        ->and($registration->fresh()->roomAssignment->method)->toBe('self');
});

it('closes self-selection after the cutoff and blocks the last-bed race', function () {
    $company = Company::create(['name' => 'Acme']);
    $past = Event::create(['company_id' => $company->id, 'title' => 'Past', 'event_date' => now()->addWeek(), 'accommodation_enabled' => true, 'accommodation_self_select_closes_at' => now()->subDay()]);
    $late = accommodationRegistration($past, 'Late');
    $this->get(route('registrations.room.select', $late->registration_code))->assertNotFound();

    $open = Event::create(['company_id' => $company->id, 'title' => 'Open', 'event_date' => now()->addWeek(), 'accommodation_enabled' => true, 'accommodation_self_select_closes_at' => now()->addDay()]);
    $first = accommodationRegistration($open, 'First');
    $second = accommodationRegistration($open, 'Second');
    $solo = accommodationRoom($open, 'Solo', 1);

    $this->post(route('registrations.room.claim', $first->registration_code), ['room_id' => $solo->id])->assertRedirect();
    $this->post(route('registrations.room.claim', $second->registration_code), ['room_id' => $solo->id])->assertRedirect();

    expect($first->fresh()->roomAssignment->accommodation_room_id)->toBe($solo->id);
    expect($second->fresh()->roomAssignment)->toBeNull();
});

it('emails the self-selection link to attendees who need a room', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Acme']);
    $manager = accommodationManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()->addWeek(), 'accommodation_enabled' => true, 'accommodation_self_select_closes_at' => now()->addDays(3)]);
    $registration = accommodationRegistration($event, 'Invitee');

    $this->actingAs($manager)->post(route('events.accommodation.invite-self-select', $event))->assertRedirect();

    Notification::assertSentTo($registration->participant, RoomSelectionInvite::class);
});

it('warns instead of sending when nobody is marked as needing a room', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Acme']);
    $manager = accommodationManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()->addWeek(), 'accommodation_enabled' => true, 'accommodation_self_select_closes_at' => now()->addDays(3)]);
    $participant = Participant::create(['company_id' => $company->id, 'name' => 'Nobody', 'phone' => '209999999']);
    EventRegistration::create(['event_id' => $event->id, 'participant_id' => $participant->id, 'status' => EventRegistration::STATUS_CONFIRMED]);

    $this->actingAs($manager)->from(route('events.accommodation.index', $event))
        ->post(route('events.accommodation.invite-self-select', $event))
        ->assertRedirect()->assertSessionHas('error');
    Notification::assertNothingSent();
});

it('bulk-marks every confirmed attendee as needing a room', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = accommodationManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Grand Summit', 'event_date' => now()]);
    $p1 = Participant::create(['company_id' => $company->id, 'name' => 'One', 'phone' => '201111112']);
    $p2 = Participant::create(['company_id' => $company->id, 'name' => 'Two', 'phone' => '202222223']);
    $r1 = EventRegistration::create(['event_id' => $event->id, 'participant_id' => $p1->id, 'status' => EventRegistration::STATUS_CONFIRMED]);
    $r2 = EventRegistration::create(['event_id' => $event->id, 'participant_id' => $p2->id, 'status' => EventRegistration::STATUS_PENDING]);

    $this->actingAs($manager)->from(route('events.accommodation.index', $event))
        ->post(route('events.accommodation.mark-all-required', $event), ['confirm_title' => 'wrong'])
        ->assertSessionHas('error');
    expect($r1->fresh()->accommodation_required)->toBeFalse();

    $this->actingAs($manager)->post(route('events.accommodation.mark-all-required', $event), ['confirm_title' => 'Grand Summit'])->assertRedirect();
    expect($r1->fresh()->accommodation_required)->toBeTrue()
        ->and($r2->fresh()->accommodation_required)->toBeFalse();
});

it('sends a fresh registrant to the room picker while self-selection is open', function () {
    $company = Company::create(['name' => 'Acme']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'slug' => 'summit-pick', 'event_date' => now()->addWeek(), 'registration_enabled' => true, 'accommodation_enabled' => true, 'accommodation_self_select_closes_at' => now()->addDays(2)]);
    accommodationRoom($event, 'A01', 4);
    $event->ensureSystemRegistrationFields();

    $response = $this->post(route('events.register.store', $event), [
        'name' => 'Picker', 'email' => 'picker@example.com', 'phone' => '0209000001',
        'gender' => 'Male', 'category' => 'General', 'accommodation_required' => 1, 'consent' => 1,
    ]);

    $registration = EventRegistration::whereHas('participant', fn ($q) => $q->where('email', 'picker@example.com'))->firstOrFail();
    $response->assertRedirect(route('registrations.room.select', ['code' => $registration->registration_code, 'new' => 1]));
    expect($registration->roomAssignment)->toBeNull();
});

it('lets a manager preview the room picker for any attendee, even before self-selection opens', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = accommodationManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now(), 'accommodation_enabled' => true]);
    accommodationRoom($event, 'A01', 2);
    $registration = accommodationRegistration($event, 'Preview Guest');

    $this->actingAs($manager)->get(route('events.accommodation.room-preview', [$event, $registration]))
        ->assertOk()
        ->assertSee('Preview Guest')
        ->assertSee('Manager preview')
        ->assertSee('A01');
});

it('blocks a manager from another company from previewing the room picker', function () {
    $company = Company::create(['name' => 'Acme']);
    $other = Company::create(['name' => 'Other']);
    $manager = accommodationManager($other);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now(), 'accommodation_enabled' => true]);
    accommodationRoom($event, 'A01', 2);
    $registration = accommodationRegistration($event, 'Preview Guest');

    $this->actingAs($manager)->get(route('events.accommodation.room-preview', [$event, $registration]))
        ->assertForbidden();
});

it('links to the room picker in the registration email when self-selection is open and a room is still needed', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Acme']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'slug' => 'summit-email', 'event_date' => now()->addWeek(), 'registration_enabled' => true, 'accommodation_enabled' => true, 'accommodation_self_select_closes_at' => now()->addDays(2)]);
    accommodationRoom($event, 'A01', 4);
    $event->ensureSystemRegistrationFields();

    $this->post(route('events.register.store', $event), [
        'name' => 'Roomy', 'email' => 'roomy@example.com', 'phone' => '0209000002',
        'gender' => 'Male', 'category' => 'General', 'accommodation_required' => 1, 'consent' => 1,
    ]);

    $registration = EventRegistration::whereHas('participant', fn ($q) => $q->where('email', 'roomy@example.com'))->firstOrFail();

    Notification::assertSentTo($registration->participant, EventRegistrationSubmitted::class, function ($notification) use ($registration) {
        $mail = $notification->toMail($registration->participant);
        expect($mail->viewData['actionUrl'])->toBe(route('registrations.room.select', $registration->registration_code))
            ->and($mail->viewData['actionLabel'])->toBe('Select your room')
            ->and($notification->toArkesel($registration->participant))->toContain(route('registrations.room.select', $registration->registration_code));

        return true;
    });
});

it('keeps the normal confirmation link in the registration email when self-selection is not open', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Acme']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'slug' => 'summit-email-2', 'event_date' => now()->addWeek(), 'registration_enabled' => true, 'accommodation_enabled' => true]);
    accommodationRoom($event, 'A01', 4);
    $event->ensureSystemRegistrationFields();

    $this->post(route('events.register.store', $event), [
        'name' => 'NoPick', 'email' => 'nopick@example.com', 'phone' => '0209000003',
        'gender' => 'Male', 'category' => 'General', 'accommodation_required' => 1, 'consent' => 1,
    ]);

    $registration = EventRegistration::whereHas('participant', fn ($q) => $q->where('email', 'nopick@example.com'))->firstOrFail();

    Notification::assertSentTo($registration->participant, EventRegistrationSubmitted::class, function ($notification) use ($registration) {
        $mail = $notification->toMail($registration->participant);
        expect($mail->viewData['actionUrl'])->toBe(route('registrations.confirmation', $registration->registration_code));

        return true;
    });
});

it('links to the room picker in the confirmed lifecycle email while self-selection is open', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Acme']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()->addWeek(), 'accommodation_enabled' => true, 'accommodation_self_select_closes_at' => now()->addDays(2)]);
    accommodationRoom($event, 'A01', 4);
    $registration = accommodationRegistration($event, 'Confirmed Guest');

    app(RegistrationLifecycleService::class)->notify($registration, 'confirmed');

    Notification::assertSentTo($registration->participant, RegistrationLifecycleNotification::class, function ($notification) use ($registration) {
        $mail = $notification->toMail($registration->participant);
        expect($mail->viewData['actionUrl'])->toBe(route('registrations.room.select', $registration->registration_code));

        return true;
    });
});

it('marks freshly imported attendees as needing a room and allocates one when self-selection is not open', function () {
    $company = Company::create(['name' => 'Acme']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Import Summit', 'event_date' => now(), 'accommodation_enabled' => true]);
    accommodationRoom($event, 'A01', 4);

    Excel::import(new UsersImport($event, true), base_path('tests/Fixtures/participants.csv'));

    $registration = EventRegistration::whereHas('participant', fn ($q) => $q->where('member_id', $company->id.':42'))->firstOrFail();
    expect($registration->accommodation_required)->toBeTrue()
        ->and($registration->roomAssignment)->not->toBeNull();
});

it('sends imported attendees a room-picker link when notified while self-selection is open', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Acme']);
    $manager = accommodationManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Import Summit', 'event_date' => now()->addWeek(), 'accommodation_enabled' => true, 'accommodation_self_select_closes_at' => now()->addDays(2)]);
    accommodationRoom($event, 'A01', 4);
    $file = UploadedFile::fake()->createWithContent('participants.csv', file_get_contents(base_path('tests/Fixtures/participants.csv')));

    $this->actingAs($manager)->post(route('events.import.store', $event), [
        'file' => $file,
        'send_notifications' => '1',
        'needs_room' => '1',
    ])->assertSessionHas('success');

    $registration = EventRegistration::whereHas('participant', fn ($q) => $q->where('member_id', $company->id.':42'))->firstOrFail();
    expect($registration->accommodation_required)->toBeTrue()
        ->and($registration->roomAssignment)->toBeNull();

    Notification::assertSentTo($registration->participant, EventRegistrationSubmitted::class, function ($notification) use ($registration) {
        $mail = $notification->toMail($registration->participant);
        expect($mail->viewData['actionUrl'])->toBe(route('registrations.room.select', $registration->registration_code));

        return true;
    });
});
