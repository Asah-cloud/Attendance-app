<?php

use App\Models\Company;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function adminManager(Company $company): User
{
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    return $manager;
}

function adminUsher(Company $company): User
{
    $usher = User::factory()->create(['company_id' => $company->id, 'role' => 'usher']);
    $usher->assignRole('usher');

    return $usher;
}

it('scopes the user listing to the manager own company', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $otherCompany = Company::create(['name' => 'Other Co']);
    $manager = adminManager($company);
    $ownUsher = adminUsher($company);
    $otherUsher = adminUsher($otherCompany);

    $this->actingAs($manager)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee($ownUsher->name)
        ->assertDontSee($otherUsher->name);
});

it('lets the super admin see users across every company', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $otherCompany = Company::create(['name' => 'Other Co']);
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');
    $ownUsher = adminUsher($company);
    $otherUsher = adminUsher($otherCompany);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee($ownUsher->name)
        ->assertSee($otherUsher->name);
});

it('allows a manager to update an usher in their own company', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = adminManager($company);
    $usher = adminUsher($company);

    $this->actingAs($manager)
        ->put(route('admin.users.update', $usher), [
            'name' => 'Updated Name',
            'email' => $usher->email,
            'role' => 'usher',
            'company_id' => $company->id,
        ])
        ->assertRedirect(route('admin.users.index'));

    expect($usher->fresh()->name)->toBe('Updated Name')
        ->and($usher->fresh()->hasRole('usher'))->toBeTrue();
});

it('prevents a manager from escalating a user to a manager role', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = adminManager($company);
    $usher = adminUsher($company);

    $this->actingAs($manager)
        ->put(route('admin.users.update', $usher), [
            'name' => $usher->name,
            'email' => $usher->email,
            'role' => 'manager',
            'company_id' => $company->id,
        ])
        ->assertForbidden();

    expect($usher->fresh()->hasRole('manager'))->toBeFalse();
});

it('prevents a manager from editing a user in another company', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $otherCompany = Company::create(['name' => 'Other Co']);
    $manager = adminManager($company);
    $usher = adminUsher($otherCompany);

    $this->actingAs($manager)
        ->get(route('admin.users.edit', $usher))
        ->assertForbidden();
});

it('allows a manager to delete an usher in their own company', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = adminManager($company);
    $usher = adminUsher($company);

    $this->actingAs($manager)
        ->delete(route('admin.users.destroy', $usher))
        ->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseMissing('users', ['id' => $usher->id]);
});

it('prevents the super admin from deleting their own account', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->delete(route('admin.users.destroy', $admin))
        ->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});
