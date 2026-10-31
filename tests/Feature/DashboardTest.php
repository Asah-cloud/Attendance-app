<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\EventAttendeeCharge;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

it('shows a manager only their company dashboard data', function () {
    $company = Company::create(['name' => 'Visible Company', 'subscription_ends_at' => now()->addMonth()]);
    $otherCompany = Company::create(['name' => 'Hidden Company', 'subscription_ends_at' => now()->addMonth()]);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $event = Event::create(['company_id' => $company->id, 'title' => 'Visible Event', 'event_date' => now()->addDay()]);
    Event::create(['company_id' => $otherCompany->id, 'title' => 'Hidden Event', 'event_date' => now()->addDay()]);
    $participant = Participant::create(['company_id' => $company->id, 'name' => 'Visible Attendee']);
    EventRegistration::create(['event_id' => $event->id, 'participant_id' => $participant->id, 'status' => 'confirmed']);

    $this->actingAs($manager)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Visible Company')
        ->assertSee('Visible Event')
        ->assertSee('Visible Attendee')
        ->assertDontSee('Hidden Company')
        ->assertDontSee('Hidden Event');
});

it('shows platform data to the super admin', function () {
    Company::create(['name' => 'Platform Company', 'subscription_ends_at' => now()->addMonth()]);
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Platform overview')
        ->assertSee('Platform Company')
        ->assertSee('Active companies');
});

it('excludes archived companies from the super admin platform stats', function () {
    $live = Company::create(['name' => 'Live Company', 'subscription_ends_at' => now()->addMonth()]);
    $liveEvent = Event::create(['company_id' => $live->id, 'title' => 'Live Event', 'event_date' => now()->addDay()]);
    $liveParticipant = Participant::create(['company_id' => $live->id, 'name' => 'Live Attendee']);
    EventRegistration::create(['event_id' => $liveEvent->id, 'participant_id' => $liveParticipant->id, 'status' => 'confirmed']);

    $archived = Company::create(['name' => 'Archived Company', 'subscription_ends_at' => now()->addMonth()]);
    $archivedEvent = Event::create(['company_id' => $archived->id, 'title' => 'Archived Event', 'event_date' => now()->addDay()]);
    Participant::create(['company_id' => $archived->id, 'name' => 'Archived Attendee']);
    $archived->delete();

    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Live Company')
        ->assertSee('Live Event')
        ->assertDontSee('Archived Company')
        ->assertDontSee('Archived Event');

    expect(Event::whereHas('company')->count())->toBe(1)
        ->and(Participant::whereHas('company')->count())->toBe(1);
});

it('allows a manager to update only their organization branding', function () {
    Storage::fake('public');
    $company = Company::create(['name' => 'Old Organization', 'subscription_ends_at' => now()->addMonth()]);
    $otherCompany = Company::create(['name' => 'Other Organization']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    $this->actingAs($manager)->patch(route('organization.branding.update'), [
        'name' => 'New Organization',
        'logo' => UploadedFile::fake()->image('logo.png', 400, 400),
    ])->assertRedirect();

    $company->refresh();
    expect($company->name)->toBe('New Organization')
        ->and($company->logo_path)->not->toBeNull()
        ->and($otherCompany->fresh()->name)->toBe('Other Organization');
    Storage::disk('public')->assertExists($company->logo_path);
});

it('prevents non managers from changing organization branding', function () {
    $user = User::factory()->create(['role' => 'usher']);

    $this->actingAs($user)->patch(route('organization.branding.update'), [
        'name' => 'Unauthorized Change',
    ])->assertForbidden();
});

it('counts people on events separately from all directory records', function () {
    $company = Company::create(['name' => 'Count Co', 'subscription_ends_at' => now()->addMonth()]);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Counted Event', 'event_date' => now()->addDay()]);
    foreach (['Registered One', 'Registered Two'] as $name) {
        $event->registrations()->create(['participant_id' => Participant::create(['company_id' => $company->id, 'name' => $name])->id, 'status' => 'confirmed']);
    }
    foreach (range(1, 3) as $i) {
        Participant::create(['company_id' => $company->id, 'name' => "Directory Only {$i}"]);
    }

    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $this->actingAs($manager)->get(route('dashboard'))->assertOk()
        ->assertSeeInOrder(['People on events', '2', '5 records in the directory']);

    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');
    $this->actingAs($admin)->get(route('dashboard'))->assertOk()
        ->assertSeeInOrder(['People on events', '2', '5 records in the directory']);
});

it('serves the admin dashboard from the cache when it lists a payment', function () {
    // The array store keeps objects as they are; the database store (used in production)
    // serializes them, so only allowlisted classes come back.
    config(['cache.default' => 'database']);
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');
    $company = Company::create(['name' => 'Paying Company', 'subscription_ends_at' => now()->addMonth()]);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Paid Event', 'event_date' => now()->addDay()]);
    EventAttendeeCharge::create([
        'event_id' => $event->id, 'company_id' => $company->id, 'status' => EventAttendeeCharge::STATUS_PAID,
        'registered_count' => 4, 'tier_breakdown' => [], 'amount_minor' => 800, 'currency' => 'GHS',
        'finalized_at' => now(), 'paid_at' => now(),
    ]);

    $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('Paid Event');
    $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('Paid Event');
});
