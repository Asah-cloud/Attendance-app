<?php

use App\Models\Company;
use App\Models\PasswordResetAudit;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function passwordActor(string $role, ?Company $company = null): User
{
    $user = User::factory()->create([
        'company_id' => $company?->id,
        'role' => $role,
        'email_verified_at' => now(),
    ]);
    $user->assignRole($role);

    return $user;
}

it('allows a manager to email a reset link only to an usher in their company', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Acme']);
    $other = Company::create(['name' => 'Other']);
    $manager = passwordActor('manager', $company);
    $usher = passwordActor('usher', $company);
    $outsideUsher = passwordActor('usher', $other);

    $this->actingAs($manager)
        ->post(route('admin.users.password.link', $usher))
        ->assertRedirect()
        ->assertSessionHas('success');

    Notification::assertSentTo($usher, ResetPassword::class);
    $this->assertDatabaseHas('password_reset_audits', [
        'user_id' => $usher->id,
        'initiated_by' => $manager->id,
        'method' => 'email_link',
    ]);

    $this->actingAs($manager)->post(route('admin.users.password.link', $outsideUsher))->assertForbidden();
});

it('lets an admin generate a one-time temporary password for managers', function () {
    $company = Company::create(['name' => 'Acme']);
    $admin = passwordActor('admin');
    $manager = passwordActor('manager', $company);
    $oldHash = $manager->password;

    $response = $this->actingAs($admin)->post(route('admin.users.password.temporary', $manager));
    $response->assertRedirect()->assertSessionHas('temporary_password');
    $temporaryPassword = session('temporary_password');

    expect($manager->fresh()->must_change_password)->toBeTrue()
        ->and($manager->fresh()->password)->not->toBe($oldHash)
        ->and(Hash::check($temporaryPassword, $manager->fresh()->password))->toBeTrue();
    $this->assertDatabaseHas('password_reset_audits', ['user_id' => $manager->id, 'method' => 'temporary_password']);
});

it('forces a temporary-password user to change it before accessing the application', function () {
    $company = Company::create(['name' => 'Acme']);
    $user = passwordActor('usher', $company);
    $user->update(['password' => Hash::make('Temporary-123!'), 'must_change_password' => true]);
    PasswordResetAudit::create(['user_id' => $user->id, 'method' => 'temporary_password']);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.force.edit'));
    $this->actingAs($user)->get(route('password.force.edit'))->assertOk()->assertSee('Create your own password');

    $this->actingAs($user)->put(route('password.force.update'), [
        'current_password' => 'Temporary-123!',
        'password' => 'A-New-Secure-Password-456!',
        'password_confirmation' => 'A-New-Secure-Password-456!',
    ])->assertRedirect(route('dashboard'));

    expect($user->fresh()->must_change_password)->toBeFalse()
        ->and(Hash::check('A-New-Secure-Password-456!', $user->fresh()->password))->toBeTrue()
        ->and(PasswordResetAudit::first()->completed_at)->not->toBeNull();
});

it('prevents resetting yourself or an administrator account', function () {
    $admin = passwordActor('admin');
    $otherAdmin = passwordActor('admin');

    $this->actingAs($admin)->post(route('admin.users.password.temporary', $admin))->assertForbidden();
    $this->actingAs($admin)->post(route('admin.users.password.temporary', $otherAdmin))->assertForbidden();
});
