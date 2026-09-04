<?php

use App\Models\Company;
use App\Models\User;
use App\Notifications\CompanyWelcomeNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

function fakePaystackForOnboarding(): void
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

            return Http::response([
                'status' => true,
                'data' => ['status' => 'success', 'amount' => 29900, 'currency' => 'GHS', 'reference' => $reference],
            ], 200);
        },
    ]);
}

function payForOnboarding(TestCase $test, string $plan, string $email): string
{
    $test->post(route('checkout.start', $plan), ['email' => $email])
        ->assertRedirect('https://paystack.test/pay/xyz');

    $reference = session('onboarding_checkout_pending')['reference'];

    $test->get(route('checkout.callback', ['reference' => $reference]))
        ->assertRedirect(route('register'));

    return $reference;
}

test('pricing displays all three test plan prices', function () {
    $this->get(route('pricing'))
        ->assertOk()
        ->assertSee('GHS 99')
        ->assertSee('GHS 299')
        ->assertSee('GHS 799');
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
    $this->post('/checkout/not-a-plan/start', ['email' => 'a@example.com'])->assertNotFound();
});

test('tampered or expired payment sessions cannot create manager accounts', function () {
    $payment = [
        'plan_key' => 'starter',
        'price_minor' => 1,
        'currency' => 'GHS',
        'payment_reference' => 'ONB-TAMPERED',
        'email' => 'manager@example.com',
        'paid_at' => now()->subHour()->toIso8601String(),
    ];

    $this->withSession(['onboarding_payment' => $payment])
        ->get(route('register'))
        ->assertRedirect(route('pricing'));
});

test('a paid onboarding creates a company manager and opens the dashboard', function () {
    Notification::fake();
    fakePaystackForOnboarding();

    payForOnboarding($this, 'business', 'manager@example.com');

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Business plan')
        ->assertSee('GHS 299.00')
        ->assertSee('manager@example.com');

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
        ->and($company->payment_reference)->toStartWith('ONB-')
        ->and($company->subscription_started_at)->not->toBeNull()
        ->and($company->subscription_ends_at)->not->toBeNull()
        ->and($manager->company_id)->toBe($company->id)
        ->and($manager->role)->toBe('manager')
        ->and($manager->hasRole('manager'))->toBeTrue()
        ->and($manager->email_verified_at)->not->toBeNull();

    $this->assertAuthenticatedAs($manager);
    Notification::assertSentTo(
        $manager,
        CompanyWelcomeNotification::class,
        fn (CompanyWelcomeNotification $notification, array $channels) => $channels === ['mail']
            && $notification->company->is($company)
    );
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

test('registering with a different email than the one paid with is rejected', function () {
    fakePaystackForOnboarding();
    payForOnboarding($this, 'business', 'paid@example.com');

    $this->post(route('register'), [
        'company_name' => 'Acme Events',
        'name' => 'Manager One',
        'email' => 'different@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
    $this->assertDatabaseCount('companies', 0);
});

test('choosing pay-per-event creates a company with no subscription and no payment record', function () {
    Notification::fake();

    $this->post(route('onboarding.pay-per-event'))
        ->assertRedirect(route('register'))
        ->assertSessionHas('onboarding_billing_mode', Company::BILLING_MODE_PAY_PER_EVENT);

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Pay per event');

    $response = $this->post(route('register'), [
        'company_name' => 'Acme Events',
        'name' => 'Manager One',
        'email' => 'manager@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'))
        ->assertSessionMissing('onboarding_billing_mode');

    $company = Company::where('name', 'Acme Events')->firstOrFail();
    $manager = User::where('email', 'manager@example.com')->firstOrFail();

    expect($company->billing_mode)->toBe(Company::BILLING_MODE_PAY_PER_EVENT)
        ->and($company->isPayPerEvent())->toBeTrue()
        ->and($company->subscription_ends_at)->toBeNull()
        ->and($company->plan_key)->toBeNull()
        ->and($manager->company_id)->toBe($company->id);

    $this->assertAuthenticatedAs($manager);
    $this->assertDatabaseCount('subscription_payments', 0);

    $this->actingAs($manager)->get(route('dashboard'))->assertOk();
});

test('a manager can upload a company logo while registering after payment', function () {
    Storage::fake('public');
    fakePaystackForOnboarding();

    payForOnboarding($this, 'business', 'manager@example.com');

    $this->post(route('register'), [
        'company_name' => 'Acme Events',
        'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
        'name' => 'Manager One',
        'email' => 'manager@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard'));

    $company = Company::where('name', 'Acme Events')->firstOrFail();

    expect($company->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($company->logo_path);
});
