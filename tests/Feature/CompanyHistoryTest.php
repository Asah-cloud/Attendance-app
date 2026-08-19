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

function historyAdmin(): User
{
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');

    return $admin;
}

it('lists archived companies with their event, attendee, and staff counts', function () {
    $admin = historyAdmin();
    $company = Company::create(['name' => 'Acme Co']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Annual Meetup', 'event_date' => now()]);
    $participant = Participant::create(['company_id' => $company->id, 'name' => 'Jane Attendee']);
    EventRegistration::create(['event_id' => $event->id, 'participant_id' => $participant->id, 'status' => 'confirmed']);
    User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);

    $company->delete();

    $this->actingAs($admin)
        ->get(route('companies.history.index'))
        ->assertOk()
        ->assertSee('Acme Co')
        ->assertSeeText('1') // events count appears somewhere in the row
        ->assertDontSee('No archived companies yet.');
});

it('shows an archived company\'s events and attendees on its history page', function () {
    $admin = historyAdmin();
    $company = Company::create(['name' => 'Acme Co']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Annual Meetup', 'event_date' => now()]);
    $participant = Participant::create(['company_id' => $company->id, 'name' => 'Jane Attendee', 'email' => 'jane@example.com']);
    EventRegistration::create(['event_id' => $event->id, 'participant_id' => $participant->id, 'status' => 'confirmed']);

    $company->delete();

    $this->actingAs($admin)
        ->get(route('companies.history.show', $company->id))
        ->assertOk()
        ->assertSee('Annual Meetup')
        ->assertSee('Jane Attendee')
        ->assertSee('jane@example.com');
});

it('prevents a manager from accessing company history', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    $this->actingAs($manager)
        ->get(route('companies.history.index'))
        ->assertForbidden();
});

it('restores an archived company and its manager\'s access', function () {
    $admin = historyAdmin();
    $company = Company::create(['name' => 'Acme Co']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $company->delete();

    $this->actingAs($admin)
        ->post(route('companies.history.restore', $company->id))
        ->assertRedirect(route('companies.index'));

    expect($company->fresh()->trashed())->toBeFalse();

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk();
});

it('permanently deletes an archived company, its events, attendees, staff, and logo file', function () {
    Storage::fake('public');
    $admin = historyAdmin();
    $company = Company::create([
        'name' => 'Acme Co',
        'logo_path' => UploadedFile::fake()->image('logo.png')->store('company-logos', 'public'),
    ]);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Annual Meetup', 'event_date' => now()]);
    $participant = Participant::create(['company_id' => $company->id, 'name' => 'Jane Attendee']);
    EventRegistration::create(['event_id' => $event->id, 'participant_id' => $participant->id, 'status' => 'confirmed']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $logoPath = $company->logo_path;

    $company->delete();

    $this->actingAs($admin)
        ->delete(route('companies.history.destroy', $company->id))
        ->assertRedirect(route('companies.history.index'));

    $this->assertDatabaseMissing('companies', ['id' => $company->id]);
    $this->assertDatabaseMissing('events', ['id' => $event->id]);
    $this->assertDatabaseMissing('participants', ['id' => $participant->id]);
    $this->assertDatabaseMissing('users', ['id' => $manager->id]);
    Storage::disk('public')->assertMissing($logoPath);
});

it('prevents a manager from permanently deleting a company', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $company->delete();

    $this->actingAs($manager)
        ->delete(route('companies.history.destroy', $company->id))
        ->assertForbidden();

    $this->assertDatabaseHas('companies', ['id' => $company->id]);
});
