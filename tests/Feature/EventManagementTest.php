<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function eventManagementManager(Company $company): User
{
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    return $manager;
}

it('allows a manager to create an event for their own company', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = eventManagementManager($company);

    $this->actingAs($manager)
        ->post(route('events.store'), [
            'title' => 'Annual Meetup',
            'event_date' => now()->addWeek()->toDateString(),
        ])
        ->assertRedirect('/events');

    $this->assertDatabaseHas('events', ['title' => 'Annual Meetup', 'company_id' => $company->id]);
});

it('ignores a manager supplied company_id and always scopes the new event to their own company', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $otherCompany = Company::create(['name' => 'Other Co']);
    $manager = eventManagementManager($company);

    $this->actingAs($manager)->post(route('events.store'), [
        'title' => 'Sneaky Event',
        'event_date' => now()->addWeek()->toDateString(),
        'company_id' => $otherCompany->id,
    ]);

    $this->assertDatabaseHas('events', ['title' => 'Sneaky Event', 'company_id' => $company->id]);
});

it('lets a manager enable rooms while creating an event; food sign-up is no longer a setting', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = eventManagementManager($company);

    $this->actingAs($manager)
        ->post(route('events.store'), [
            'title' => 'Retreat',
            'event_date' => now()->addWeek()->toDateString(),
            'accommodation_enabled' => '1',
            'food_registration_required' => '1',
        ])
        ->assertRedirect('/events');

    // Food registration was removed: every confirmed participant is meal-eligible,
    // so the flag is always stored false regardless of what the form submits.
    $this->assertDatabaseHas('events', [
        'title' => 'Retreat',
        'accommodation_enabled' => true,
        'food_registration_required' => false,
    ]);
});

it('leaves rooms and food sign-up off by default when creating an event', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = eventManagementManager($company);

    $this->actingAs($manager)->post(route('events.store'), [
        'title' => 'Plain Service',
        'event_date' => now()->addWeek()->toDateString(),
    ]);

    $this->assertDatabaseHas('events', [
        'title' => 'Plain Service',
        'accommodation_enabled' => false,
        'food_registration_required' => false,
    ]);
});

it('lets a manager toggle rooms and food sign-up when editing an event', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = eventManagementManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now(), 'accommodation_enabled' => true, 'food_registration_required' => true]);

    $this->actingAs($manager)
        ->put(route('events.update', $event), [
            'title' => 'Conference',
            'event_date' => now()->toDateString(),
            // omitted accommodation_enabled/food_registration_required = unchecked
        ])
        ->assertRedirect('/events');

    expect($event->fresh()->accommodation_enabled)->toBeFalse()
        ->and($event->fresh()->food_registration_required)->toBeFalse();
});

it('blocks event creation once a company reaches its event limit', function () {
    $company = Company::create(['name' => 'Acme Co', 'event_limit' => 1]);
    $manager = eventManagementManager($company);
    Event::create(['company_id' => $company->id, 'title' => 'Existing Event', 'event_date' => now()]);

    $this->actingAs($manager)
        ->post(route('events.store'), [
            'title' => 'Over Limit Event',
            'event_date' => now()->addWeek()->toDateString(),
        ])
        ->assertSessionHas('error');

    $this->assertDatabaseCount('events', 1);
});

it('allows a manager to update their own event', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = eventManagementManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Old Title', 'event_date' => now()]);

    $this->actingAs($manager)
        ->put(route('events.update', $event), [
            'title' => 'New Title',
            'event_date' => now()->toDateString(),
        ])
        ->assertRedirect('/events');

    expect($event->fresh()->title)->toBe('New Title');
});

it('prevents a manager from updating another company event', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $otherCompany = Company::create(['name' => 'Other Co']);
    $manager = eventManagementManager($company);
    $event = Event::create(['company_id' => $otherCompany->id, 'title' => 'Private', 'event_date' => now()]);

    $this->actingAs($manager)
        ->put(route('events.update', $event), [
            'title' => 'Hacked',
            'event_date' => now()->toDateString(),
        ])
        ->assertForbidden();

    expect($event->fresh()->title)->toBe('Private');
});

it('allows a manager to cancel their own event', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Acme Co']);
    $manager = eventManagementManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Cancel Me', 'event_date' => now()]);

    $this->actingAs($manager)
        ->patch(route('events.cancel', $event))
        ->assertRedirect('/events');

    expect($event->fresh()->cancelled_at)->not->toBeNull();
});

it('prevents a manager from cancelling another company event', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $otherCompany = Company::create(['name' => 'Other Co']);
    $manager = eventManagementManager($company);
    $event = Event::create(['company_id' => $otherCompany->id, 'title' => 'Private', 'event_date' => now()]);

    $this->actingAs($manager)
        ->patch(route('events.cancel', $event))
        ->assertForbidden();

    expect($event->fresh()->cancelled_at)->toBeNull();
});

it('allows a manager to delete their own event', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = eventManagementManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Delete Me', 'event_date' => now()]);

    $this->actingAs($manager)
        ->delete(route('events.destroy', $event))
        ->assertRedirect('/events');

    $this->assertDatabaseMissing('events', ['id' => $event->id]);
});

it('prevents a manager from deleting another company event', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $otherCompany = Company::create(['name' => 'Other Co']);
    $manager = eventManagementManager($company);
    $event = Event::create(['company_id' => $otherCompany->id, 'title' => 'Private', 'event_date' => now()]);

    $this->actingAs($manager)
        ->delete(route('events.destroy', $event))
        ->assertForbidden();

    $this->assertDatabaseHas('events', ['id' => $event->id]);
});

it('prevents an usher from managing events', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $usher = User::factory()->create(['company_id' => $company->id, 'role' => 'usher']);
    $usher->assignRole('usher');

    $this->actingAs($usher)
        ->post(route('events.store'), [
            'title' => 'Not Allowed',
            'event_date' => now()->addWeek()->toDateString(),
        ])
        ->assertForbidden();
});
