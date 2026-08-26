<?php

use App\Models\Company;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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

function fakePaystackForBilling(): void
{
    config()->set('services.paystack', [
        'secret_key' => 'sk_test_123',
        'public_key' => 'pk_test_123',
        'base_url' => 'https://paystack.test',
    ]);

    Http::fake([
        'paystack.test/transaction/initialize' => Http::response([
            'status' => true,
            'data' => ['authorization_url' => 'https://paystack.test/pay/xyz', 'reference' => 'ignored'],
        ], 200),
        'paystack.test/transaction/verify/*' => function ($request) {
            $reference = Str::afterLast($request->url(), '/');
            $payment = SubscriptionPayment::where('payment_reference', $reference)->first();

            return Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'amount' => $payment?->amount_minor,
                    'currency' => $payment?->currency,
                    'reference' => $reference,
                ],
            ], 200);
        },
    ]);
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
    fakePaystackForBilling();
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
        ->post(route('billing.checkout.start', 'business'))
        ->assertRedirect('https://paystack.test/pay/xyz');

    $payment = SubscriptionPayment::firstOrFail();
    expect($payment->status)->toBe(SubscriptionPayment::STATUS_PENDING);

    $this->actingAs($manager)
        ->get(route('billing.checkout.callback', ['reference' => $payment->payment_reference]))
        ->assertRedirect(route('billing.index'));

    $company->refresh();
    $payment->refresh();

    expect($company->plan_key)->toBe('business')
        ->and($company->plan_price_minor)->toBe(29900)
        ->and($company->event_limit)->toBe(15)
        ->and($company->subscription_ends_at->isFuture())->toBeTrue()
        ->and($company->subscription_auto_renews)->toBeTrue()
        ->and($company->subscription_cancelled_at)->toBeNull()
        ->and($payment->type)->toBe('plan_change')
        ->and($payment->status)->toBe(SubscriptionPayment::STATUS_PAID)
        ->and($payment->amount_minor)->toBe(29900)
        ->and($payment->payment_reference)->toBe($company->payment_reference);
});

it('extends an active subscription from its current end date', function () {
    fakePaystackForBilling();
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

    $this->actingAs($manager)->post(route('billing.checkout.start', 'starter'));
    $payment = SubscriptionPayment::firstOrFail();
    $this->actingAs($manager)->get(route('billing.checkout.callback', ['reference' => $payment->payment_reference]));

    expect($company->fresh()->subscription_ends_at->toDateString())
        ->toBe($originalEnd->copy()->addMonth()->toDateString())
        ->and($payment->fresh()->type)->toBe('renewal');
});

it('does not update the subscription when Paystack reports the payment failed', function () {
    config()->set('services.paystack', ['secret_key' => 'sk_test_123', 'public_key' => 'pk_test_123', 'base_url' => 'https://paystack.test']);
    Http::fake([
        'paystack.test/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://paystack.test/pay/xyz']], 200),
        'paystack.test/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'failed']], 200),
    ]);
    $company = Company::create(['name' => 'Failing Co', 'is_active' => true, 'plan_key' => 'starter', 'plan_price_minor' => 9900, 'billing_currency' => 'GHS']);
    $manager = billingManager($company);

    $this->actingAs($manager)->post(route('billing.checkout.start', 'business'));
    $payment = SubscriptionPayment::firstOrFail();

    $this->actingAs($manager)
        ->get(route('billing.checkout.callback', ['reference' => $payment->payment_reference]))
        ->assertRedirect(route('billing.checkout', 'business'));

    expect($payment->fresh()->status)->toBe(SubscriptionPayment::STATUS_FAILED)
        ->and($company->fresh()->plan_key)->toBe('starter');
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

it('never redirects a pay-per-event company to billing regardless of subscription fields', function () {
    $company = Company::create([
        'name' => 'Pay Per Event Co',
        'is_active' => true,
        'billing_mode' => Company::BILLING_MODE_PAY_PER_EVENT,
        'subscription_ends_at' => now()->subYear(),
    ]);
    $manager = billingManager($company);

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk();

    $this->actingAs($manager)
        ->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Pay per event');
});

it('switches a pay-per-event company back to a subscription when it pays for a plan', function () {
    fakePaystackForBilling();
    $company = Company::create([
        'name' => 'Switching Co',
        'is_active' => true,
        'billing_mode' => Company::BILLING_MODE_PAY_PER_EVENT,
    ]);
    $manager = billingManager($company);

    $this->actingAs($manager)
        ->post(route('billing.checkout.start', 'starter'))
        ->assertRedirect('https://paystack.test/pay/xyz');

    $payment = SubscriptionPayment::firstOrFail();
    $this->actingAs($manager)
        ->get(route('billing.checkout.callback', ['reference' => $payment->payment_reference]))
        ->assertRedirect(route('billing.index'));

    expect($company->fresh()->billing_mode)->toBe(Company::BILLING_MODE_SUBSCRIPTION)
        ->and($company->fresh()->plan_key)->toBe('starter');
});

it('prevents ushers from accessing company billing', function () {
    $company = Company::create(['name' => 'One']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('usher');

    $this->actingAs($user)->get(route('billing.index'))->assertForbidden();
    $this->actingAs($user)->post(route('billing.checkout.start', 'enterprise'))->assertForbidden();
});
