<?php

use App\Models\Company;
use App\Models\User;

test('pricing displays all three test plan prices', function () {
    $this->get(route('pricing'))
        ->assertOk()
        ->assertSee('GHS 99')
        ->assertSee('GHS 299')
        ->assertSee('GHS 799')
        ->assertSee('Test mode is active');
});

test('manager registration requires a completed payment session', function () {
    $this->get(route('register'))->assertRedirect(route('pricing'));

    $this->post(route('register'), [
        'company_name' => 'Acme Events',
        'name' => 'Manager One',
        'email' => 'manager@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('pricing'));

    $this->assertGuest();
    $this->assertDatabaseCount('companies', 0);
    $this->assertDatabaseCount('users', 0);
});

test('an unknown plan cannot enter checkout', function () {
    $this->get('/checkout/not-a-plan')->assertNotFound();
    $this->post('/checkout/not-a-plan/test-payment')->assertNotFound();
});

test('tampered or expired payment sessions cannot create manager accounts', function () {
    $payment = [
        'plan_key' => 'starter',
        'price_minor' => 1,
        'currency' => 'GHS',
        'payment_reference' => 'TEST-TAMPERED',
        'paid_at' => now()->subHour()->toIso8601String(),
    ];

    $this->withSession(['onboarding_payment' => $payment])
        ->get(route('register'))
        ->assertRedirect(route('pricing'));
});

test('a paid onboarding creates a company manager and opens the dashboard', function () {
    $this->post(route('checkout.test-payment', 'business'))
        ->assertRedirect(route('register'))
        ->assertSessionHas('onboarding_payment');

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Business plan')
        ->assertSee('GHS 299.00');

    $response = $this->post(route('register'), [
        'company_name' => 'Acme Events',
        'name' => 'Manager One',
        'email' => 'manager@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'))
        ->assertSessionMissing('onboarding_payment');

    $company = Company::where('name', 'Acme Events')->firstOrFail();
    $manager = User::where('email', 'manager@example.com')->firstOrFail();

    expect($company->plan_key)->toBe('business')
        ->and($company->plan_price_minor)->toBe(29900)
        ->and($company->billing_currency)->toBe('GHS')
        ->and($company->event_limit)->toBe(15)
        ->and($company->payment_reference)->toStartWith('TEST-')
        ->and($company->subscription_started_at)->not->toBeNull()
        ->and($company->subscription_ends_at)->not->toBeNull()
        ->and($manager->company_id)->toBe($company->id)
        ->and($manager->role)->toBe('manager')
        ->and($manager->hasRole('manager'))->toBeTrue()
        ->and($manager->email_verified_at)->not->toBeNull();

    $this->assertAuthenticatedAs($manager);
    $this->assertDatabaseHas('subscription_payments', [
        'company_id' => $company->id,
        'plan_key' => 'business',
        'type' => 'initial',
        'amount_minor' => 29900,
        'currency' => 'GHS',
        'payment_reference' => $company->payment_reference,
        'status' => 'paid',
    ]);
});
