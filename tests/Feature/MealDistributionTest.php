<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MealDistribution;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\MealStockLow;
use Illuminate\Support\Facades\Notification;
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

function mealRegistration(Event $event, string $name = 'Food Guest', ?string $category = null): EventRegistration
{
    $participant = Participant::create(array_filter([
        'company_id' => $event->company_id,
        'name' => $name,
        'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
        'category' => $category,
    ]));

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

it('auto-approves scans within a category entitlement and blocks beyond it', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = mealUser('manager', $company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);
    $registration = mealRegistration($event, 'VIP Guest', 'VIP');
    $meal = MealDistribution::create(['event_id' => $event->id, 'name' => 'Lunch', 'total_portions' => 10]);
    $meal->entitlements()->create(['category' => 'VIP', 'portions_allowed' => 2]);

    $this->actingAs($manager)->postJson(route('events.meals.issue', [$event, $meal]), ['registration_code' => $registration->registration_code])->assertOk();
    $this->actingAs($manager)->postJson(route('events.meals.issue', [$event, $meal]), ['registration_code' => $registration->registration_code])->assertOk();
    $this->actingAs($manager)->postJson(route('events.meals.issue', [$event, $meal]), ['registration_code' => $registration->registration_code])->assertStatus(409);

    $this->assertDatabaseHas('meal_collections', ['meal_distribution_id' => $meal->id, 'event_registration_id' => $registration->id, 'quantity' => 2]);

    $this->actingAs($manager)->postJson(route('events.meals.issue', [$event, $meal]), [
        'registration_code' => $registration->registration_code, 'override' => true, 'override_reason' => 'Extra',
    ])->assertOk();
    $this->assertDatabaseHas('meal_collections', ['meal_distribution_id' => $meal->id, 'event_registration_id' => $registration->id, 'quantity' => 3, 'was_overridden' => true]);
});

it('still blocks a second scan by default for a category with no configured entitlement', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = mealUser('manager', $company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);
    $registration = mealRegistration($event, 'Regular Guest', 'General');
    $meal = MealDistribution::create(['event_id' => $event->id, 'name' => 'Lunch', 'total_portions' => 10]);

    $this->actingAs($manager)->postJson(route('events.meals.issue', [$event, $meal]), ['registration_code' => $registration->registration_code])->assertOk();
    $this->actingAs($manager)->postJson(route('events.meals.issue', [$event, $meal]), ['registration_code' => $registration->registration_code])->assertStatus(409);
});

it('lets a manager replace a distribution entitlements from a textarea', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = mealUser('manager', $company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);
    $meal = MealDistribution::create(['event_id' => $event->id, 'name' => 'Lunch', 'total_portions' => 10]);
    $meal->entitlements()->create(['category' => 'Stale', 'portions_allowed' => 5]);

    $this->actingAs($manager)->patch(route('events.meals.update', [$event, $meal]), [
        'name' => 'Lunch', 'total_portions' => 10, 'is_active' => 1,
        'entitlements' => "VIP:2\nStaff:1",
    ])->assertRedirect();

    $this->assertDatabaseMissing('meal_entitlements', ['meal_distribution_id' => $meal->id, 'category' => 'Stale']);
    $this->assertDatabaseHas('meal_entitlements', ['meal_distribution_id' => $meal->id, 'category' => 'VIP', 'portions_allowed' => 2]);
    $this->assertDatabaseHas('meal_entitlements', ['meal_distribution_id' => $meal->id, 'category' => 'Staff', 'portions_allowed' => 1]);
});

it('saves dietary notes through the attendee edit flow and shows them in the food report', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = mealUser('manager', $company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);
    $registration = mealRegistration($event);
    $meal = MealDistribution::create(['event_id' => $event->id, 'name' => 'Lunch', 'total_portions' => 10]);
    $this->actingAs($manager)->postJson(route('events.meals.issue', [$event, $meal]), ['registration_code' => $registration->registration_code])->assertOk();

    $this->actingAs($manager)->patch(route('events.registrations.participant.update', [$event, $registration]), [
        'name' => $registration->participant->name,
        'email' => $registration->participant->email,
        'gender' => 'Male',
        'category' => 'Member',
        'dietary_notes' => 'Vegetarian',
    ])->assertRedirect();

    expect($registration->participant->fresh()->dietary_notes)->toBe('Vegetarian');
    $this->actingAs($manager)->get(route('events.meals.report', $event))->assertOk()->assertSee('Vegetarian');
});

it('records the serving station on a collection and totals them in the report', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = mealUser('manager', $company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);
    $registration = mealRegistration($event);
    $meal = MealDistribution::create(['event_id' => $event->id, 'name' => 'Lunch', 'total_portions' => 10]);

    $this->actingAs($manager)->post(route('events.meals.stations.update', $event), ['stations' => "Gate A\nGate B"])->assertRedirect();
    $station = $event->mealStations()->where('name', 'Gate A')->firstOrFail();

    $this->actingAs($manager)->postJson(route('events.meals.issue', [$event, $meal]), [
        'registration_code' => $registration->registration_code,
        'meal_station_id' => $station->id,
    ])->assertOk();

    $this->assertDatabaseHas('meal_collections', ['event_registration_id' => $registration->id, 'meal_station_id' => $station->id]);
    $this->actingAs($manager)->get(route('events.meals.report', $event))->assertOk()->assertSee('Gate A');
});

it('lets a manager assign a portion allocation to a station and blocks issuing beyond it', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = mealUser('manager', $company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);
    $meal = MealDistribution::create(['event_id' => $event->id, 'name' => 'Lunch', 'total_portions' => 10]);
    $this->actingAs($manager)->post(route('events.meals.stations.update', $event), ['stations' => "Gate A\nGate B"])->assertRedirect();
    $gateA = $event->mealStations()->where('name', 'Gate A')->firstOrFail();
    $gateB = $event->mealStations()->where('name', 'Gate B')->firstOrFail();

    $this->actingAs($manager)->put(route('events.meals.stations.allocations.update', [$event, $meal]), [
        'allocations' => [$gateA->id => 1, $gateB->id => ''],
    ])->assertRedirect();

    $this->assertDatabaseHas('meal_station_allocations', ['meal_distribution_id' => $meal->id, 'meal_station_id' => $gateA->id, 'allocated_portions' => 1]);
    $this->assertDatabaseMissing('meal_station_allocations', ['meal_distribution_id' => $meal->id, 'meal_station_id' => $gateB->id]);

    $first = mealRegistration($event, 'First Guest');
    $second = mealRegistration($event, 'Second Guest');
    $third = mealRegistration($event, 'Third Guest');

    $this->actingAs($manager)->postJson(route('events.meals.issue', [$event, $meal]), [
        'registration_code' => $first->registration_code,
        'meal_station_id' => $gateA->id,
    ])->assertOk();

    $this->actingAs($manager)->postJson(route('events.meals.issue', [$event, $meal]), [
        'registration_code' => $second->registration_code,
        'meal_station_id' => $gateA->id,
    ])->assertStatus(422)->assertJsonPath('message', 'No portions remain allocated to this station.');

    // Unallocated station and no station still draw from the shared 10-portion stock.
    $this->actingAs($manager)->postJson(route('events.meals.issue', [$event, $meal]), [
        'registration_code' => $second->registration_code,
        'meal_station_id' => $gateB->id,
    ])->assertOk();
    $this->actingAs($manager)->postJson(route('events.meals.issue', [$event, $meal]), [
        'registration_code' => $third->registration_code,
    ])->assertOk();

    expect($meal->fresh()->remainingPortions())->toBe(7)
        ->and($meal->fresh()->remainingPortionsAtStation($gateA->id))->toBe(0)
        ->and($meal->fresh()->remainingPortionsAtStation($gateB->id))->toBeNull();
});

it('lets a manager override a station allocation cap', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = mealUser('manager', $company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);
    $meal = MealDistribution::create(['event_id' => $event->id, 'name' => 'Lunch', 'total_portions' => 10]);
    $this->actingAs($manager)->post(route('events.meals.stations.update', $event), ['stations' => 'Gate A'])->assertRedirect();
    $gateA = $event->mealStations()->where('name', 'Gate A')->firstOrFail();
    $this->actingAs($manager)->put(route('events.meals.stations.allocations.update', [$event, $meal]), ['allocations' => [$gateA->id => 1]])->assertRedirect();

    $first = mealRegistration($event, 'First Guest');
    $second = mealRegistration($event, 'Second Guest');
    $this->actingAs($manager)->postJson(route('events.meals.issue', [$event, $meal]), ['registration_code' => $first->registration_code, 'meal_station_id' => $gateA->id])->assertOk();

    $this->actingAs($manager)->postJson(route('events.meals.issue', [$event, $meal]), [
        'registration_code' => $second->registration_code,
        'meal_station_id' => $gateA->id,
        'override' => 1,
        'override_reason' => 'Ran out early, replenished on the spot',
    ])->assertOk();

    expect($meal->fresh()->issuedPortionsAtStation($gateA->id))->toBe(2);
});

it('reports remaining/low-stock status and alerts managers once when stock dips', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Acme']);
    $manager = mealUser('manager', $company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);
    $meal = MealDistribution::create(['event_id' => $event->id, 'name' => 'Lunch', 'total_portions' => 2, 'low_stock_threshold' => 1]);

    $status = $this->actingAs($manager)->getJson(route('events.meals.status', [$event, $meal]))->assertOk()->json();
    expect($status)->toMatchArray(['remaining' => 2, 'issued' => 0, 'total' => 2, 'low_stock' => false, 'is_open' => true]);

    $registration = mealRegistration($event);
    $this->actingAs($manager)->postJson(route('events.meals.issue', [$event, $meal]), ['registration_code' => $registration->registration_code])->assertOk();

    Notification::assertSentTo($manager, MealStockLow::class);
    expect($meal->fresh()->low_stock_notified_at)->not->toBeNull();

    $second = mealRegistration($event, 'Second Guest');
    $this->actingAs($manager)->postJson(route('events.meals.issue', [$event, $meal]), ['registration_code' => $second->registration_code])->assertOk();
    Notification::assertSentToTimes($manager, MealStockLow::class, 1);
});

it('resets the low-stock alert guard when stock is raised back above the threshold', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = mealUser('manager', $company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);
    $meal = MealDistribution::create(['event_id' => $event->id, 'name' => 'Lunch', 'total_portions' => 2, 'low_stock_threshold' => 1, 'low_stock_notified_at' => now()]);

    $this->actingAs($manager)->patch(route('events.meals.update', [$event, $meal]), ['name' => 'Lunch', 'total_portions' => 5, 'is_active' => 1])->assertRedirect();

    expect($meal->fresh()->low_stock_notified_at)->toBeNull();
});

it('computes a food forecast from confirmed attendance and category entitlements', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = mealUser('manager', $company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);
    mealRegistration($event, 'VIP One', 'VIP');
    mealRegistration($event, 'VIP Two', 'VIP');
    mealRegistration($event, 'Regular One', 'General');
    $meal = MealDistribution::create(['event_id' => $event->id, 'name' => 'Lunch', 'total_portions' => 10]);
    $meal->entitlements()->create(['category' => 'VIP', 'portions_allowed' => 2]);

    $response = $this->actingAs($manager)->get(route('events.meals.report', $event))->assertOk();
    // 2 VIP guests * 2 portions + 1 General guest * default 1 portion = 5
    $response->assertSee('5');
});

it('renders one printable voucher per confirmed attendee with the shared attendance QR', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = mealUser('manager', $company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);
    mealRegistration($event, 'Voucher Guest');

    $this->actingAs($manager)->get(route('events.meals.vouchers', $event))
        ->assertOk()
        ->assertSee('Voucher Guest')
        ->assertSee('Food voucher');
});

it('logs waste and reflects it in the food report totals', function () {
    $company = Company::create(['name' => 'Acme']);
    $manager = mealUser('manager', $company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()]);
    $meal = MealDistribution::create(['event_id' => $event->id, 'name' => 'Lunch', 'total_portions' => 10]);

    $this->actingAs($manager)->post(route('events.meals.waste', [$event, $meal]), [
        'quantity' => 3, 'reason' => 'Spoiled before service',
    ])->assertRedirect();

    $this->assertDatabaseHas('meal_waste_logs', ['meal_distribution_id' => $meal->id, 'quantity' => 3, 'reason' => 'Spoiled before service', 'logged_by' => $manager->id]);
    $this->actingAs($manager)->get(route('events.meals.report', $event))->assertOk()->assertSee('Spoiled before service');
});
