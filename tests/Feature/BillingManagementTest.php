<?php

use App\Models\Company;
use App\Models\SubscriptionPayment;
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

it('allows an expired manager to reach billing while redirecting event access', function () {
    $company = Company::create([
        'name' => 'Expired Company',
        'is_active' => true,
        'subscription_ends_at' => now()->subDay(),
        'plan_key' => 'starter',
        'plan_price_minor' => 9900,
        'billing_currency' => 'GHS',
    ]);
    $manager = billingManager($company);

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertRedirect(route('billing.index'));

    $this->actingAs($manager)
        ->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Expired Company')
        ->assertSee('Expired');
});

it('renews an expired subscription and records the payment', function () {
    $company = Company::create([
        'name' => 'Renewing Company',
        'is_active' => true,
        'subscription_ends_at' => now()->subWeek(),
        'plan_key' => 'starter',
        'plan_price_minor' => 9900,
        'billing_currency' => 'GHS',
        'event_limit' => 3,
        'subscription_auto_renews' => false,
    ]);
    $manager = billingManager($company);

    $this->actingAs($manager)
        ->post(route('billing.test-payment', 'business'))
        ->assertRedirect(route('billing.index'));

    $company->refresh();
    $payment = SubscriptionPayment::firstOrFail();

    expect($company->plan_key)->toBe('business')
        ->and($company->plan_price_minor)->toBe(29900)
        ->and($company->event_limit)->toBe(15)
        ->and($company->subscription_ends_at->isFuture())->toBeTrue()
        ->and($company->subscription_auto_renews)->toBeTrue()
        ->and($company->subscription_cancelled_at)->toBeNull()
        ->and($payment->type)->toBe('plan_change')
        ->and($payment->amount_minor)->toBe(29900)
        ->and($payment->payment_reference)->toBe($company->payment_reference);
});

it('extends an active subscription from its current end date', function () {
    $originalEnd = now()->addDays(10)->startOfDay();
    $company = Company::create([
        'name' => 'Active Company',
        'is_active' => true,
        'subscription_ends_at' => $originalEnd,
        'plan_key' => 'starter',
        'plan_price_minor' => 9900,
        'billing_currency' => 'GHS',
    ]);
    $manager = billingManager($company);

    $this->actingAs($manager)->post(route('billing.test-payment', 'starter'));

    expect($company->fresh()->subscription_ends_at->toDateString())
        ->toBe($originalEnd->copy()->addMonth()->toDateString())
        ->and(SubscriptionPayment::firstOrFail()->type)->toBe('renewal');
});

it('allows a manager to update billing contact and renewal preference', function () {
    $company = Company::create([
        'name' => 'Managed Company',
        'is_active' => true,
        'subscription_auto_renews' => true,
    ]);
    $manager = billingManager($company);

    $this->actingAs($manager)
        ->patch(route('billing.contact.update'), ['email' => 'billing@example.com'])
        ->assertRedirect();
    $this->actingAs($manager)->post(route('billing.cancel'))->assertRedirect();

    expect($company->fresh()->email)->toBe('billing@example.com')
        ->and($company->fresh()->subscription_auto_renews)->toBeFalse()
        ->and($company->fresh()->subscription_cancelled_at)->not->toBeNull();

    $this->actingAs($manager)->post(route('billing.resume'))->assertRedirect();

    expect($company->fresh()->subscription_auto_renews)->toBeTrue()
        ->and($company->fresh()->subscription_cancelled_at)->toBeNull();
});

it('prevents ushers from accessing company billing', function () {
    $company = Company::create(['name' => 'One']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('usher');

    $this->actingAs($user)->get(route('billing.index'))->assertForbidden();
    $this->actingAs($user)->post(route('billing.test-payment', 'enterprise'))->assertForbidden();
});
