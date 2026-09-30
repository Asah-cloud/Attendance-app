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

function eventVisibilityManager(Company $company): User
{
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    return $manager;
}

it('hides a closed event from the manager event list by default, and shows it with the past filter', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = eventVisibilityManager($company);
    $closed = Event::create(['company_id' => $company->id, 'title' => 'Last Month', 'event_date' => now()->subMonth()]);
    $upcoming = Event::create(['company_id' => $company->id, 'title' => 'Next Week', 'event_date' => now()->addWeek()]);

    $this->actingAs($manager)
        ->get(route('events.index'))
        ->assertOk()
        ->assertSee('Next Week')
        ->assertDontSee('Last Month')
        ->assertSee('Show past events');

    $this->actingAs($manager)
        ->get(route('events.index', ['past' => 1]))
        ->assertOk()
        ->assertSee('Next Week')
        ->assertSee('Last Month')
        ->assertSee('Hide past events');

    expect($closed->status)->toBe('closed')
        ->and($upcoming->status)->toBe('upcoming');
});

it('hides a closed event from an usher event list by default', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $usher = User::factory()->create(['company_id' => $company->id, 'role' => 'usher']);
    $usher->assignRole('usher');
    $closed = Event::create(['company_id' => $company->id, 'title' => 'Old Event', 'event_date' => now()->subMonth()]);
    $usher->events()->attach($closed);
    $active = Event::create(['company_id' => $company->id, 'title' => 'Today Event', 'event_date' => now()]);
    $usher->events()->attach($active);

    $this->actingAs($usher)
        ->get(route('events.index'))
        ->assertOk()
        ->assertSee('Today Event')
        ->assertDontSee('Old Event');

    $this->actingAs($usher)
        ->get(route('events.index', ['past' => 1]))
        ->assertOk()
        ->assertSee('Old Event');
});

it('hides a closed event from the admin per-company event list by default', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');
    Event::create(['company_id' => $company->id, 'title' => 'Closed One', 'event_date' => now()->subMonth()]);
    Event::create(['company_id' => $company->id, 'title' => 'Upcoming One', 'event_date' => now()->addWeek()]);

    $this->actingAs($admin)
        ->get(route('events.index'))
        ->assertOk()
        ->assertSee('Upcoming One')
        ->assertDontSee('Closed One');

    $this->actingAs($admin)
        ->get(route('events.index', ['past' => 1]))
        ->assertOk()
        ->assertSee('Closed One');
});

it('still counts every event toward the company slot usage, even when closed ones are hidden', function () {
    $company = Company::create(['name' => 'Acme Co', 'event_limit' => 5]);
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');
    Event::create(['company_id' => $company->id, 'title' => 'Closed One', 'event_date' => now()->subMonth()]);
    Event::create(['company_id' => $company->id, 'title' => 'Upcoming One', 'event_date' => now()->addWeek()]);

    $this->actingAs($admin)
        ->get(route('events.index'))
        ->assertOk()
        ->assertSee('2 of 5 event slots used');
});
