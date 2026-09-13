<?php

use App\Models\AccommodationRoom;
use App\Models\Company;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use App\Services\EventBillingService;
use App\Services\EventRegistrationResolver;
use App\Support\BadgeDesign;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function supportStaffManager(Company $company): User
{
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    return $manager;
}

it('imports one company staff identity and assigns its reusable qr to selected events', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = supportStaffManager($company);
    $first = Event::create(['company_id' => $company->id, 'title' => 'Event One', 'event_date' => today(), 'end_date' => today()]);
    $second = Event::create(['company_id' => $company->id, 'title' => 'Event Two', 'event_date' => today(), 'end_date' => today()]);
    $file = UploadedFile::fake()->createWithContent('staff.csv', "Name,Department,Category\nAma Mensah,Protocol,Staff\n");

    $this->actingAs($manager)->post(route('support-staff.import'), [
        'file' => $file,
        'event_ids' => [$first->id, $second->id],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $staff = Participant::where('is_support_staff', true)->firstOrFail();
    expect($staff->department)->toBe('Protocol')
        ->and($staff->staff_code)->toStartWith('STF-')
        ->and($staff->registrations()->count())->toBe(2);

    $this->actingAs($manager)->post(route('support-staff.import'), [
        'file' => UploadedFile::fake()->createWithContent('staff-again.csv', "Name,Department,Category\nAma Mensah,Protocol,Staff\n"),
        'event_ids' => [$first->id],
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect(Participant::where('is_support_staff', true)->count())->toBe(1);

    $payload = 'ASAH-STAFF:'.$staff->staff_qr_token;
    expect(app(EventRegistrationResolver::class)->fromScan($first, $payload)?->participant_id)->toBe($staff->id)
        ->and(app(EventRegistrationResolver::class)->fromScan($second, $payload)?->participant_id)->toBe($staff->id)
        ->and(BadgeDesign::qrPayload($first->registrations()->first()))->toBe($payload);
});

it('lets the super admin choose a company and import its event staff', function () {
    $firstCompany = Company::create(['name' => 'First Company']);
    $secondCompany = Company::create(['name' => 'Second Company']);
    $event = Event::create(['company_id' => $secondCompany->id, 'title' => 'Second Event', 'event_date' => today()]);
    $admin = User::factory()->create(['company_id' => null, 'role' => 'admin']);
    $admin->assignRole('admin');

    $this->actingAs($admin)->get(route('support-staff.index', ['company_id' => $secondCompany->id]))
        ->assertOk()->assertSee('Second Company');

    $this->actingAs($admin)->post(route('support-staff.import'), [
        'company_id' => $secondCompany->id,
        'file' => UploadedFile::fake()->createWithContent('staff.csv', "Name,Department,Category\nAkosua Boateng,Protocol,Staff\n"),
        'event_ids' => [$event->id],
    ])->assertRedirect(route('support-staff.index', ['company_id' => $secondCompany->id]));

    expect(Participant::where('company_id', $secondCompany->id)->where('is_support_staff', true)->count())->toBe(1)
        ->and(Participant::where('company_id', $firstCompany->id)->count())->toBe(0);
});

it('prevents the super admin from assigning staff to an event from another company', function () {
    $firstCompany = Company::create(['name' => 'First Company']);
    $secondCompany = Company::create(['name' => 'Second Company']);
    $event = Event::create(['company_id' => $secondCompany->id, 'title' => 'Second Event', 'event_date' => today()]);
    $admin = User::factory()->create(['company_id' => null, 'role' => 'admin']);
    $admin->assignRole('admin');

    $this->actingAs($admin)->post(route('support-staff.import'), [
        'company_id' => $firstCompany->id,
        'file' => UploadedFile::fake()->createWithContent('staff.csv', "Name,Department,Category\nAkosua Boateng,Protocol,Staff\n"),
        'event_ids' => [$event->id],
    ])->assertForbidden();

    expect(Participant::where('is_support_staff', true)->count())->toBe(0);
});

it('uses the same staff qr to record attendance in separate events', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = supportStaffManager($company);
    $staff = Participant::create(['company_id' => $company->id, 'name' => 'Ama', 'department' => 'Protocol', 'category' => 'Staff', 'is_support_staff' => true, 'staff_code' => 'STF-000001', 'staff_qr_token' => str_repeat('a', 48)]);
    $events = collect(['One', 'Two'])->map(fn ($title) => Event::create(['company_id' => $company->id, 'title' => $title, 'event_date' => today()]));
    $events->each(fn ($event) => $event->registrations()->create(['participant_id' => $staff->id, 'status' => 'confirmed']));

    foreach ($events as $event) {
        $this->actingAs($manager)->postJson(route('events.scanner.check-in', $event), [
            'registration_code' => 'ASAH-STAFF:'.$staff->staff_qr_token,
        ])->assertOk()->assertJson(['successful' => true, 'name' => 'Ama']);
    }

    expect($staff->attendances()->count())->toBe(2);
});

it('prints department and staff id values and excludes support staff from attendee billing', function () {
    $company = Company::create(['name' => 'Acme']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Event', 'event_date' => today()]);
    $staff = Participant::create(['company_id' => $company->id, 'name' => 'Ama', 'department' => 'Protocol', 'category' => 'Staff', 'is_support_staff' => true, 'staff_code' => 'STF-000001', 'staff_qr_token' => str_repeat('b', 48)]);
    $attendee = Participant::create(['company_id' => $company->id, 'name' => 'Kojo', 'category' => 'Member']);
    $registration = $event->registrations()->create(['participant_id' => $staff->id, 'status' => 'confirmed']);
    $event->registrations()->create(['participant_id' => $attendee->id, 'status' => 'confirmed']);

    expect(BadgeDesign::values($event, $registration)['department'])->toBe('Protocol')
        ->and(BadgeDesign::values($event, $registration)['member'])->toBe('STF-000001')
        ->and(app(EventBillingService::class)->estimate($event)['registered_count'])->toBe(1);
});

it('reserves every room on a floor and preserves reservations when inventory is copied', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = supportStaffManager($company);
    $source = Event::create(['company_id' => $company->id, 'title' => 'One', 'event_date' => today()]);
    $destination = Event::create(['company_id' => $company->id, 'title' => 'Two', 'event_date' => today()->addDay()]);
    $site = $source->accommodationSites()->create(['name' => 'Campus']);
    $block = $site->blocks()->create(['name' => 'Staff Block', 'category_restriction' => 'Staff']);
    $floor = $block->floors()->create(['name' => 'Staff Floor']);
    $floor->rooms()->createMany([['name' => 'S1', 'capacity' => 2], ['name' => 'S2', 'capacity' => 2]]);

    $this->actingAs($manager)->patch(route('events.accommodation.floors.update', [$source, $floor]), [
        'name' => 'Staff Floor', 'priority' => 100, 'is_active' => 1, 'room_status' => 'reserved',
    ])->assertRedirect();
    expect($floor->rooms()->where('status', AccommodationRoom::STATUS_RESERVED)->count())->toBe(2);

    $this->post(route('events.accommodation.clone', $destination), ['source_event_id' => $source->id])->assertRedirect();
    expect(AccommodationRoom::whereHas('floor.block.site', fn ($q) => $q->where('event_id', $destination->id))->where('status', AccommodationRoom::STATUS_RESERVED)->count())->toBe(2);
});
