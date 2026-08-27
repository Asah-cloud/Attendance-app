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

function navigationUser(string $role, Company $company): User
{
    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => $role,
        'email_verified_at' => now(),
    ]);
    $user->assignRole($role);

    return $user;
}

it('shows breadcrumbs and complete event workspace navigation to managers', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = navigationUser('manager', $company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Annual Summit', 'event_date' => now()]);

    $this->actingAs($manager)
        ->get(route('events.attendance', $event))
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Annual Summit')
        ->assertSeeInOrder(['Attendance', 'Attendees', 'Forms', 'Reports', 'Settings'])
        ->assertSee(route('events.registrations.index', $event), false)
        ->assertDontSee(route('events.registration-form.edit', $event), false)
        ->assertSee(route('reports.event', $event), false);
});

it('groups registration and confirmations inside the forms page', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = navigationUser('manager', $company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Annual Summit', 'event_date' => now()]);

    $this->actingAs($manager)
        ->get(route('events.forms.index', $event))
        ->assertOk()
        ->assertSee('Registration form')
        ->assertSee('Confirmations')
        ->assertSee(route('events.registration-form.edit', $event), false)
        ->assertSee(route('events.confirmations.index', $event), false);
});

it('limits event workspace navigation for ushers to authorized operational pages', function () {
    $company = Company::create(['name' => 'Acme']);
    $usher = navigationUser('usher', $company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Annual Summit', 'event_date' => now()]);
    $usher->events()->attach($event);

    $this->actingAs($usher)
        ->get(route('events.attendance', $event))
        ->assertOk()
        ->assertSee(route('events.attendance', $event), false)
        ->assertDontSee(route('events.registrations.index', $event), false)
        ->assertDontSee(route('events.registration-form.edit', $event), false)
        ->assertDontSee(route('events.edit', $event), false);
});

it('keeps authenticated team creation inside the application navigation', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = navigationUser('manager', $company);

    $this->actingAs($manager)
        ->get(route('admin.register-person'))
        ->assertOk()
        ->assertSee('data-ui="app"', false)
        ->assertSee('Cancel')
        ->assertSee(route('admin.users.index'), false)
        ->assertDontSee('Back to System Login');
});
