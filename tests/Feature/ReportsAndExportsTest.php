<?php

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

function reportsManager(Company $company): User
{
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    return $manager;
}

function reportsEventWithAttendee(Company $company): Event
{
    $event = Event::create(['company_id' => $company->id, 'title' => 'Reported Event', 'event_date' => now()]);
    $participant = Participant::create(['company_id' => $company->id, 'name' => 'Attendee']);
    $event->registrations()->create(['participant_id' => $participant->id, 'status' => 'confirmed']);

    return $event;
}

it('allows a manager to view the summary report for their own event', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = reportsManager($company);
    $event = reportsEventWithAttendee($company);

    $this->actingAs($manager)
        ->get(route('reports.summary', $event))
        ->assertOk()
        ->assertSee('Attendee');
});

it('prevents a manager from viewing the summary report for another company event', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $otherCompany = Company::create(['name' => 'Other Co']);
    $manager = reportsManager($company);
    $event = reportsEventWithAttendee($otherCompany);

    $this->actingAs($manager)
        ->get(route('reports.summary', $event))
        ->assertForbidden();
});

it('allows a manager to download the summary report export', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = reportsManager($company);
    $event = reportsEventWithAttendee($company);

    $response = $this->actingAs($manager)->get(route('reports.summary.export', $event));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('.xlsx');
});

it('allows a manager to export event attendance as excel', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = reportsManager($company);
    $event = reportsEventWithAttendee($company);

    $response = $this->actingAs($manager)->get(route('reports.excel', ['event' => $event, 'day' => 1]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('.xlsx');
});

it('allows a manager to export event attendance as csv', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = reportsManager($company);
    $event = reportsEventWithAttendee($company);

    $response = $this->actingAs($manager)->get(route('reports.csv', ['event' => $event, 'day' => 1]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('.csv');
});

it('prevents a manager from exporting another company event attendance', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $otherCompany = Company::create(['name' => 'Other Co']);
    $manager = reportsManager($company);
    $event = reportsEventWithAttendee($otherCompany);

    $this->actingAs($manager)
        ->get(route('reports.csv', ['event' => $event, 'day' => 1]))
        ->assertForbidden();
});

it('rejects an out of range day for the attendance export', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = reportsManager($company);
    $event = reportsEventWithAttendee($company);

    $this->actingAs($manager)
        ->get(route('reports.csv', ['event' => $event, 'day' => 99]))
        ->assertSessionHasErrors('day');
});
