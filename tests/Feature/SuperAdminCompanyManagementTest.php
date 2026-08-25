<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function companyManagementAdmin(): User
{
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');

    return $admin;
}

it('allows the super admin to list companies', function () {
    $admin = companyManagementAdmin();
    $company = Company::create(['name' => 'Acme Co']);

    $this->actingAs($admin)
        ->get(route('companies.index'))
        ->assertOk()
        ->assertSee('Acme Co');
});

it('allows the super admin to create a company', function () {
    $admin = companyManagementAdmin();

    $this->actingAs($admin)
        ->post(route('companies.store'), [
            'name' => 'New Co',
            'billing_mode' => Company::BILLING_MODE_SUBSCRIPTION,
            'subscription_ends_at' => now()->addYear()->toDateString(),
            'event_limit' => 10,
        ])
        ->assertRedirect(route('companies.index'));

    $this->assertDatabaseHas('companies', ['name' => 'New Co', 'event_limit' => 10]);
});

it('allows the super admin to update a company', function () {
    $admin = companyManagementAdmin();
    $company = Company::create(['name' => 'Acme Co', 'event_limit' => 5]);

    $this->actingAs($admin)
        ->put(route('companies.update', $company), [
            'name' => 'Acme Co Renamed',
            'billing_mode' => Company::BILLING_MODE_SUBSCRIPTION,
            'subscription_ends_at' => now()->addYear()->toDateString(),
            'event_limit' => 20,
            'is_active' => true,
        ])
        ->assertRedirect(route('companies.index'));

    expect($company->fresh()->name)->toBe('Acme Co Renamed')
        ->and($company->fresh()->event_limit)->toBe(20);
});

it('allows the super admin to upload a company logo while creating a company', function () {
    Storage::fake('public');
    $admin = companyManagementAdmin();

    $this->actingAs($admin)
        ->post(route('companies.store'), [
            'name' => 'New Co',
            'billing_mode' => Company::BILLING_MODE_SUBSCRIPTION,
            'subscription_ends_at' => now()->addYear()->toDateString(),
            'event_limit' => 10,
            'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
        ])
        ->assertRedirect(route('companies.index'));

    $company = Company::where('name', 'New Co')->firstOrFail();
    expect($company->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($company->logo_path);
});

it('allows the super admin to replace and remove a company logo while editing a company', function () {
    Storage::fake('public');
    $admin = companyManagementAdmin();
    $company = Company::create(['name' => 'Acme Co', 'event_limit' => 5]);

    $this->actingAs($admin)->put(route('companies.update', $company), [
        'name' => $company->name,
        'billing_mode' => Company::BILLING_MODE_SUBSCRIPTION,
        'subscription_ends_at' => now()->addYear()->toDateString(),
        'event_limit' => $company->event_limit,
        'is_active' => true,
        'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
    ])->assertRedirect(route('companies.index'));

    $company->refresh();
    $firstLogoPath = $company->logo_path;
    expect($firstLogoPath)->not->toBeNull();
    Storage::disk('public')->assertExists($firstLogoPath);

    $this->actingAs($admin)->put(route('companies.update', $company), [
        'name' => $company->name,
        'billing_mode' => Company::BILLING_MODE_SUBSCRIPTION,
        'subscription_ends_at' => now()->addYear()->toDateString(),
        'event_limit' => $company->event_limit,
        'is_active' => true,
        'remove_logo' => '1',
    ])->assertRedirect(route('companies.index'));

    expect($company->refresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($firstLogoPath);
});

it('archives a company instead of permanently deleting it, and hides it from the normal list', function () {
    $admin = companyManagementAdmin();
    $company = Company::create(['name' => 'Acme Co']);

    $this->actingAs($admin)
        ->delete(route('companies.destroy', $company))
        ->assertRedirect(route('companies.index'));

    $this->assertDatabaseHas('companies', ['id' => $company->id]);
    expect($company->fresh()->trashed())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('companies.index'))
        ->assertDontSee('Acme Co');
});

it('immediately blocks an archived company\'s manager from the app', function () {
    $admin = companyManagementAdmin();
    $company = Company::create(['name' => 'Acme Co']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    $this->actingAs($admin)->delete(route('companies.destroy', $company));

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertForbidden();
});

it('prevents a manager from accessing company management', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    $this->actingAs($manager)
        ->get(route('companies.index'))
        ->assertForbidden();
});
