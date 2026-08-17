<?php

use App\Models\Company;
use App\Models\Event;
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
        ->assertSee('Active subscriptions');
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
    $user = User::factory()->create(['role' => 'regular']);

    $this->actingAs($user)->patch(route('organization.branding.update'), [
        'name' => 'Unauthorized Change',
    ])->assertForbidden();
});
