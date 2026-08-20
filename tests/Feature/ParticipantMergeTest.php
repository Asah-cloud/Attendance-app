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

function mergeManager(Company $company): User
{
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    return $manager;
}

it('lets a manager search for participants within their own company', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $otherCompany = Company::create(['name' => 'Other Co']);
    $manager = mergeManager($company);
    Participant::create(['company_id' => $company->id, 'name' => 'Jane Doe', 'phone' => '201234567']);
    Participant::create(['company_id' => $otherCompany->id, 'name' => 'Jane Elsewhere', 'phone' => '209999999']);

    $this->actingAs($manager)
        ->get(route('participants.duplicates.index', ['q' => 'Jane']))
        ->assertOk()
        ->assertSee('Jane Doe')
        ->assertDontSee('Jane Elsewhere');
});

it('merges two participants, filling gaps, moving registrations/attendance, and deleting the duplicate', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = mergeManager($company);
    $eventA = Event::create(['company_id' => $company->id, 'title' => 'Event A', 'event_date' => now()]);
    $eventB = Event::create(['company_id' => $company->id, 'title' => 'Event B', 'event_date' => now()]);

    $primary = Participant::create(['company_id' => $company->id, 'name' => 'Jane Doe', 'phone' => '201234567', 'category' => 'Member']);
    $duplicate = Participant::create(['company_id' => $company->id, 'name' => 'Jane D', 'email' => 'jane@example.com', 'phone' => '201234567']);

    // Both registered for Event A (conflict - primary's should win, duplicate's discarded)
    $primary->registrations()->create(['event_id' => $eventA->id, 'status' => 'confirmed']);
    $duplicateRegA = $duplicate->registrations()->create(['event_id' => $eventA->id, 'status' => 'confirmed']);
    // Only the duplicate registered for Event B (no conflict - should move to primary)
    $duplicateRegB = $duplicate->registrations()->create(['event_id' => $eventB->id, 'status' => 'confirmed']);

    Attendance::create(['event_id' => $eventB->id, 'participant_id' => $duplicate->id, 'day' => 1, 'status' => 'present']);

    $this->actingAs($manager)
        ->post(route('participants.duplicates.merge'), [
            'primary_id' => $primary->id,
            'all_ids' => [$primary->id, $duplicate->id],
        ])
        ->assertRedirect(route('participants.duplicates.index'));

    $primary->refresh();
    expect($primary->email)->toBe('jane@example.com') // gap filled from duplicate
        ->and($primary->registrations()->where('event_id', $eventA->id)->count())->toBe(1) // no duplicate row
        ->and($primary->registrations()->where('event_id', $eventB->id)->exists())->toBeTrue() // moved over
        ->and($primary->attendances()->where('event_id', $eventB->id)->exists())->toBeTrue()
        ->and(Participant::find($duplicate->id))->toBeNull()
        ->and($primary->auditLogs()->whereJsonContainsKey('changes->merged')->exists())->toBeTrue();

    $this->assertDatabaseMissing('event_registrations', ['id' => $duplicateRegA->id]);
    $this->assertDatabaseHas('event_registrations', ['id' => $duplicateRegB->id, 'participant_id' => $primary->id]);
});

it('prevents a manager from merging a participant belonging to another company', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $otherCompany = Company::create(['name' => 'Other Co']);
    $manager = mergeManager($company);
    $primary = Participant::create(['company_id' => $company->id, 'name' => 'Jane Doe']);
    $outsider = Participant::create(['company_id' => $otherCompany->id, 'name' => 'Outsider']);

    $this->actingAs($manager)
        ->post(route('participants.duplicates.merge'), [
            'primary_id' => $primary->id,
            'all_ids' => [$primary->id, $outsider->id],
        ])
        ->assertNotFound();

    expect(Participant::find($outsider->id))->not->toBeNull();
});

it('prevents an usher from accessing the duplicate merge tool', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $usher = User::factory()->create(['company_id' => $company->id, 'role' => 'usher']);
    $usher->assignRole('usher');

    $this->actingAs($usher)->get(route('participants.duplicates.index'))->assertForbidden();
});
