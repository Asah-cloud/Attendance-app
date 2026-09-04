<?php

use App\Models\Company;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\PaystackService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function integrationSettingsAdmin(): User
{
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');

    return $admin;
}

it('shows integration settings as not configured when nothing is stored', function () {
    $this->actingAs(integrationSettingsAdmin())
        ->get(route('integrations.edit'))
        ->assertOk()
        ->assertSee('Not configured')
        ->assertDontSee('sk_test_secret_value');
});

it('lets an admin store a paystack secret key and shows only a masked preview afterwards', function () {
    $admin = integrationSettingsAdmin();

    $this->actingAs($admin)
        ->put(route('integrations.update'), ['paystack_secret_key' => 'sk_test_secret_value'])
        ->assertRedirect();

    expect(PlatformSetting::get('paystack_secret_key'))->toBe('sk_test_secret_value');

    $this->actingAs($admin)
        ->get(route('integrations.edit'))
        ->assertOk()
        ->assertSee('lue') // last 4 chars of the preview
        ->assertDontSee('sk_test_secret_value');
});

it('keeps the existing key when the field is submitted blank', function () {
    PlatformSetting::set('paystack_secret_key', 'sk_test_original');
    $admin = integrationSettingsAdmin();

    $this->actingAs($admin)
        ->put(route('integrations.update'), ['paystack_secret_key' => ''])
        ->assertRedirect();

    expect(PlatformSetting::get('paystack_secret_key'))->toBe('sk_test_original');
});

it('encrypts the stored value at rest', function () {
    PlatformSetting::set('paystack_secret_key', 'sk_test_original');

    $raw = DB::table('platform_settings')->where('key', 'paystack_secret_key')->value('value');

    expect($raw)->not->toContain('sk_test_original');
});

it('prevents a manager from viewing or updating integration settings', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    $this->actingAs($manager)->get(route('integrations.edit'))->assertForbidden();
    $this->actingAs($manager)->put(route('integrations.update'), ['paystack_secret_key' => 'x'])->assertForbidden();
});

it('makes PaystackService use the database-stored key instead of the env value', function () {
    config()->set('services.paystack', ['secret_key' => 'sk_env_fallback', 'public_key' => null, 'base_url' => 'https://paystack.test']);
    PlatformSetting::set('paystack_secret_key', 'sk_db_override');
    Http::fake(['paystack.test/*' => Http::response(['status' => true, 'data' => ['status' => 'success']], 200)]);

    app(PaystackService::class)->verify('SOME-REF');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk_db_override'));
});

it('falls back to the env value when no key is stored', function () {
    config()->set('services.paystack', ['secret_key' => 'sk_env_fallback', 'public_key' => null, 'base_url' => 'https://paystack.test']);
    Http::fake(['paystack.test/*' => Http::response(['status' => true, 'data' => ['status' => 'success']], 200)]);

    app(PaystackService::class)->verify('SOME-REF');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk_env_fallback'));
});
