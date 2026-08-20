<?php

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

it('shows category and gender attendance breakdowns aggregated across all of a company\'s events', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    $eventA = Event::create(['company_id' => $company->id, 'title' => 'Sunday Service', 'event_date' => now()]);
    $eventB = Event::create(['company_id' => $company->id, 'title' => 'Midweek Service', 'event_date' => now()]);

    $male = Participant::create(['company_id' => $company->id, 'name' => 'Male Member', 'category' => 'Member', 'gender' => 'Male']);
    $female = Participant::create(['company_id' => $company->id, 'name' => 'Female Guest', 'category' => 'Guest', 'gender' => 'Female']);
    $unspecified = Participant::create(['company_id' => $company->id, 'name' => 'No Gender', 'category' => 'Member']);

    // Same "Member/Male" attendee checks in at two different events - should sum to 2.
    Attendance::create(['event_id' => $eventA->id, 'participant_id' => $male->id, 'day' => 1, 'status' => 'present']);
    Attendance::create(['event_id' => $eventB->id, 'participant_id' => $male->id, 'day' => 1, 'status' => 'present']);
    Attendance::create(['event_id' => $eventA->id, 'participant_id' => $female->id, 'day' => 1, 'status' => 'present']);
    Attendance::create(['event_id' => $eventB->id, 'participant_id' => $unspecified->id, 'day' => 1, 'status' => 'present']);

    $response = $this->actingAs($manager)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Member · 3', false)
        ->assertSee('Guest · 1', false)
        ->assertSee('Male · 2', false)
        ->assertSee('Female · 1', false)
        ->assertSee('Unspecified · 1', false);
});

it('shows an empty state when a company has no attendance yet', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('No attendance recorded yet.');
});
