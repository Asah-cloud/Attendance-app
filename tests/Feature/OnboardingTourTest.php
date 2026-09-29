<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

it('auto-starts the dashboard tour for a manager who has never seen it', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-tour-key="dashboard"', false)
        ->assertSee('data-auto-start-tour="true"', false)
        ->assertSee('data-tour="create-event"', false)
        ->assertSee('data-tour="stats"', false);
});

it('does not auto-start a tour a manager has already seen', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'manager',
        'onboarding_tours_seen' => ['dashboard'],
    ]);
    $manager->assignRole('manager');

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-auto-start-tour="false"', false);

    // A different page's tour is unaffected by having seen the dashboard one.
    $this->actingAs($manager)
        ->get(route('events.index'))
        ->assertOk()
        ->assertSee('data-tour-key="events-index"', false)
        ->assertSee('data-auto-start-tour="true"', false);
});

it('resolves the right tour key for a page inside an event', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);

    $this->actingAs($manager)
        ->get(route('events.attendance', $event))
        ->assertOk()
        ->assertSee('data-tour-key="event-attendance"', false)
        ->assertSee('data-tour="event-tabs"', false);

    $this->actingAs($manager)
        ->get(route('events.billing.show', $event))
        ->assertOk()
        ->assertSee('data-tour-key="event-billing"', false);
});

it('never auto-starts a tour for non-manager roles', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $usher = User::factory()->create(['company_id' => $company->id, 'role' => 'usher']);
    $usher->assignRole('usher');

    $this->actingAs($usher)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-auto-start-tour="false"', false)
        ->assertDontSee('data-tour-restart', false);
});

it('has no tour, and no restart button, on a page with no defined tour', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    $this->actingAs($manager)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('data-tour-key=""', false)
        ->assertSee('data-auto-start-tour="false"', false)
        ->assertDontSee('data-tour-restart', false);
});

it('lets an authenticated user mark a specific tour as seen, without affecting others', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    $this->actingAs($manager)
        ->postJson(route('tour.complete'), ['tour' => 'dashboard'])
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    expect($manager->fresh()->onboarding_tours_seen)->toBe(['dashboard'])
        ->and($manager->fresh()->hasSeenTour('dashboard'))->toBeTrue()
        ->and($manager->fresh()->hasSeenTour('billing'))->toBeFalse();

    $this->actingAs($manager)->postJson(route('tour.complete'), ['tour' => 'billing'])->assertOk();
    expect($manager->fresh()->onboarding_tours_seen)->toBe(['dashboard', 'billing']);

    // Marking the same one again doesn't duplicate it.
    $this->actingAs($manager)->postJson(route('tour.complete'), ['tour' => 'billing'])->assertOk();
    expect($manager->fresh()->onboarding_tours_seen)->toBe(['dashboard', 'billing']);
});

it('rejects an unknown tour key', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    $this->actingAs($manager)
        ->postJson(route('tour.complete'), ['tour' => 'not-a-real-tour'])
        ->assertStatus(422);
});

it('requires authentication to mark the tour complete', function () {
    $this->postJson(route('tour.complete'), ['tour' => 'dashboard'])->assertUnauthorized();
});
