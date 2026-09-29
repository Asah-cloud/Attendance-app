<?php

use App\Models\Company;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

it('auto-starts the tour for a manager who has never seen it', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-auto-start-tour="true"', false)
        ->assertSee('data-tour="create-event"', false)
        ->assertSee('data-tour="stats"', false);
});

it('does not auto-start the tour once a manager has completed it', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'manager',
        'onboarding_tour_completed_at' => now(),
    ]);
    $manager->assignRole('manager');

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-auto-start-tour="false"', false);
});

it('never auto-starts the tour for non-manager roles', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $usher = User::factory()->create(['company_id' => $company->id, 'role' => 'usher']);
    $usher->assignRole('usher');

    $this->actingAs($usher)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-auto-start-tour="false"', false)
        ->assertDontSee('data-tour-restart', false);
});

it('lets an authenticated user mark the tour as complete', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    expect($manager->onboarding_tour_completed_at)->toBeNull();

    $this->actingAs($manager)
        ->postJson(route('tour.complete'))
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    expect($manager->fresh()->onboarding_tour_completed_at)->not->toBeNull();
});

it('requires authentication to mark the tour complete', function () {
    $this->postJson(route('tour.complete'))->assertUnauthorized();
});
