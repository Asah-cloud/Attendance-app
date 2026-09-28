<?php

use App\Models\Company;
use App\Models\User;
use App\Notifications\CompanyWelcomeNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

test('pricing displays the pay-per-event tiers and advanced features', function () {
    $this->get(route('pricing'))
        ->assertOk()
        ->assertSee('No subscription')
        ->assertSee('GHS 2.00')
        ->assertSee('Custom Messages');
});

test('manager registration requires starting from the pricing page', function () {
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

test('subscription checkout no longer exists', function () {
    $this->get('/checkout/business')->assertNotFound();
    $this->post('/checkout/business/start', ['email' => 'a@example.com'])->assertNotFound();
});

test('registering creates a free pay-per-event company and manager and opens the dashboard', function () {
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
        ->and($company->payment_reference)->toBeNull()
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
    $this->assertDatabaseCount('subscription_payments', 0);

    $this->actingAs($manager)->get(route('dashboard'))->assertOk();
});

test('a manager can upload a company logo while registering', function () {
    Storage::fake('public');
    $this->post(route('onboarding.pay-per-event'));

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
