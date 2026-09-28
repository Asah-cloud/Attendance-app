<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\EventAttendeeCharge;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function billingManager(Company $company): User
{
    $manager = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'manager',
    ]);
    $manager->assignRole('manager');

    return $manager;
}

it('shows a manager their event bills and no subscription controls', function () {
    $company = Company::create(['name' => 'Billed Co', 'is_active' => true]);
    $manager = billingManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Billed Conference', 'event_date' => now()->addWeek()]);
    EventAttendeeCharge::create([
        'event_id' => $event->id,
        'company_id' => $company->id,
        'status' => EventAttendeeCharge::STATUS_PENDING_PAYMENT,
        'registered_count' => 4,
        'tier_breakdown' => [],
        'amount_minor' => 800,
        'currency' => 'GHS',
        'finalized_at' => now(),
    ]);

    $this->actingAs($manager)
        ->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Billed Conference')
        ->assertSee('pending payment')
        ->assertSee('8.00')
        ->assertDontSee('Renew')
        ->assertDontSee('Change to this plan')
        ->assertDontSee('Automatic renewal');
});

it('shows the billing page with no bills yet', function () {
    $company = Company::create(['name' => 'New Co', 'is_active' => true]);

    $this->actingAs(billingManager($company))
        ->get(route('billing.index'))
        ->assertOk()
        ->assertSee('No event bills yet');
});

it('allows a manager to update the billing contact', function () {
    $company = Company::create(['name' => 'Managed Company', 'is_active' => true]);

    $this->actingAs(billingManager($company))
        ->patch(route('billing.contact.update'), ['email' => 'billing@example.com'])
        ->assertRedirect();

    expect($company->fresh()->email)->toBe('billing@example.com');
});

it('never locks a company out because of an old subscription end date', function () {
    $company = Company::create([
        'name' => 'Old Subscriber',
        'is_active' => true,
        'billing_mode' => Company::BILLING_MODE_SUBSCRIPTION,
        'subscription_ends_at' => now()->subYear(),
    ]);
    $manager = billingManager($company);

    $this->actingAs($manager)->get(route('dashboard'))->assertOk();
    $this->actingAs($manager)->get(route('events.create'))->assertOk();
});

it('no longer has subscription checkout or renewal routes', function () {
    expect(Route::has('billing.checkout'))->toBeFalse()
        ->and(Route::has('billing.checkout.start'))->toBeFalse()
        ->and(Route::has('billing.cancel'))->toBeFalse()
        ->and(Route::has('checkout'))->toBeFalse();
});

it('prevents ushers from accessing company billing', function () {
    $company = Company::create(['name' => 'One']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('usher');

    $this->actingAs($user)->get(route('billing.index'))->assertForbidden();
});
