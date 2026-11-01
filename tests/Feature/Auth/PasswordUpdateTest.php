<?php

use App\Models\User;
use App\Notifications\SignInCodeNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('updatePassword', 'current_password')
        ->assertRedirect('/profile');
});

test('code sign-in users set a password with an emailed code instead of a current password', function () {
    Notification::fake();
    $user = User::factory()->create(['signs_in_with_code' => true]);

    $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Sign-in Code')->assertSee('Email me a code')->assertDontSee('Current Password');

    $this->from('/profile')->post(route('password.code'))->assertRedirect('/profile')->assertSessionHas('status', 'password-code-sent');
    $code = null;
    Notification::assertSentTo($user, SignInCodeNotification::class, function ($notification) use (&$code) {
        $code = $notification->code;

        return $notification->forPassword;
    });

    $this->from('/profile')->put('/password', ['code' => $code, 'password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->assertSessionHasNoErrors()->assertRedirect('/profile');

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue()
        ->and($user->sign_in_code)->toBeNull();
});

test('a wrong or missing code does not change a code sign-in user\'s password', function () {
    $user = User::factory()->create(['signs_in_with_code' => true]);
    $user->forceFill(['sign_in_code' => Hash::make('123456'), 'sign_in_code_expires_at' => now()->addMinutes(15)])->save();

    $this->actingAs($user)->from('/profile')->put('/password', ['code' => '654321', 'password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->assertSessionHasErrorsIn('updatePassword', 'code');
    $this->from('/profile')->put('/password', ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->assertSessionHasErrorsIn('updatePassword', 'code');

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});

test('only code sign-in users can be emailed a password code', function () {
    $this->actingAs(User::factory()->create())->post(route('password.code'))->assertNotFound();
});
