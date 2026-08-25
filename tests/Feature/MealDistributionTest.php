<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MealDistribution;
use App\Models\Participant;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function mealUser(string $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id, 'role' => $role, 'email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function mealRegistration(Event $event, string $name = 'Food Guest'): EventRegistration
{
    $participant = Participant::create(['company_id' => $event->company_id, 'name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)).'@example.com']);

    return EventRegistration::create([
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'status' => EventRegistration::STATUS_CONFIRMED,
    ]);
}

it('lets managers create food distributions for their events', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = mealUser('manager', $company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);

    $this->actingAs($manager)->post(route('events.meals.store', $event), [
        'name' => 'Day 1 Lunch',
        'total_portions' => 100,
        'is_active' => 1,
    ])->assertRedirect();

    $this->assertDatabaseHas('meal_distributions', ['event_id' => $event->id, 'name' => 'Day 1 Lunch', 'total_portions' => 100]);
});

it('issues one portion from the existing attendee QR and blocks a duplicate', function () {
    $company = Company::create(['name' => 'Acme']);
    $usher = mealUser('usher', $company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);
    $usher->events()->attach($event);
    $registration = mealRegistration($event);
    $meal = MealDistribution::create(['event_id' => $event->id, 'name' => 'Lunch', 'total_portions' => 2]);

    $this->actingAs($usher)->postJson(route('events.meals.issue', [$event, $meal]), [
        'registration_code' => 'ASAH-ATTENDANCE:'.$registration->registration_code,
    ])->assertOk()->assertJsonPath('successful', true);

    $this->actingAs($usher)->postJson(route('events.meals.issue', [$event, $meal]), [
        'registration_code' => $registration->registration_code,
    ])->assertStatus(409)->assertJsonPath('successful', false);

    $this->assertDatabaseHas('meal_collections', [
        'meal_distribution_id' => $meal->id,
        'event_registration_id' => $registration->id,
        'quantity' => 1,
        'issued_by' => $usher->id,
    ]);
    expect($meal->fresh()->remainingPortions())->toBe(1);
});

it('enforces stock and confirmed registration requirements', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = mealUser('manager', $company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);
    $first = mealRegistration($event, 'First Guest');
    $second = mealRegistration($event, 'Second Guest');
    $pending = mealRegistration($event, 'Pending Guest');
    $pending->update(['status' => EventRegistration::STATUS_PENDING]);
    $meal = MealDistribution::create(['event_id' => $event->id, 'name' => 'Lunch', 'total_portions' => 1]);

    $this->actingAs($manager)->postJson(route('events.meals.issue', [$event, $meal]), ['registration_code' => $first->registration_code])->assertOk();
    $this->actingAs($manager)->postJson(route('events.meals.issue', [$event, $meal]), ['registration_code' => $second->registration_code])->assertStatus(422)->assertJsonPath('message', 'No portions remain for this distribution.');
    $this->actingAs($manager)->postJson(route('events.meals.issue', [$event, $meal]), ['registration_code' => $pending->registration_code])->assertStatus(422);
});

it('allows only managers to issue an audited extra portion and reverse a collection', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = mealUser('manager', $company);
    $usher = mealUser('usher', $company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);
    $usher->events()->attach($event);
    $registration = mealRegistration($event);
    $meal = MealDistribution::create(['event_id' => $event->id, 'name' => 'Lunch', 'total_portions' => 1]);

    $this->actingAs($usher)->postJson(route('events.meals.issue', [$event, $meal]), ['registration_code' => $registration->registration_code])->assertOk();
    $this->actingAs($usher)->postJson(route('events.meals.issue', [$event, $meal]), ['registration_code' => $registration->registration_code, 'override' => true, 'override_reason' => 'Extra'])->assertForbidden();
    $this->actingAs($manager)->postJson(route('events.meals.issue', [$event, $meal]), ['registration_code' => $registration->registration_code, 'override' => true, 'override_reason' => 'Speaker allowance'])->assertOk();

    $collection = $meal->collections()->firstOrFail();
    expect($collection->quantity)->toBe(2)
        ->and($collection->was_overridden)->toBeTrue()
        ->and($collection->override_reason)->toBe('Speaker allowance');

    $this->actingAs($manager)->delete(route('events.meals.collections.reverse', [$event, $meal, $collection]))->assertRedirect();
    $this->assertDatabaseHas('meal_collections', ['id' => $collection->id, 'quantity' => 1]);
    $this->assertDatabaseHas('meal_collection_audits', ['meal_distribution_id' => $meal->id, 'action' => 'override', 'quantity_change' => 1]);
    $this->assertDatabaseHas('meal_collection_audits', ['meal_distribution_id' => $meal->id, 'action' => 'reversed', 'quantity_change' => -1]);
});

it('provides managers with food reports and prevents stock below issued portions', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = mealUser('manager', $company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);
    $registration = mealRegistration($event);
    $meal = MealDistribution::create(['event_id' => $event->id, 'name' => 'Lunch', 'total_portions' => 3]);

    $this->actingAs($manager)->postJson(route('events.meals.issue', [$event, $meal]), ['registration_code' => $registration->registration_code])->assertOk();
    $this->actingAs($manager)->get(route('events.meals.report', $event))->assertOk()->assertSee('Food distribution report')->assertSee('Food Guest');
    $this->actingAs($manager)->get(route('events.meals.report.csv', $event))->assertOk();
    $this->actingAs($manager)->get(route('events.meals.report.pdf', $event))->assertOk()->assertHeader('content-type', 'application/pdf');

    $this->actingAs($manager)->patch(route('events.meals.update', [$event, $meal]), ['name' => 'Lunch', 'total_portions' => 0, 'is_active' => 1])->assertSessionHasErrors('total_portions');
});

it('prevents staff from accessing another event meal', function () {
    $company = Company::create(['name' => 'Acme']);
    $otherCompany = Company::create(['name' => 'Other']);
    $manager = mealUser('manager', $company);
    $otherEvent = Event::create(['company_id' => $otherCompany->id, 'title' => 'Other Summit', 'event_date' => now()]);
    $meal = MealDistribution::create(['event_id' => $otherEvent->id, 'name' => 'Lunch', 'total_portions' => 10]);

    $this->actingAs($manager)->get(route('events.meals.scanner', [$otherEvent, $meal]))->assertForbidden();
});
