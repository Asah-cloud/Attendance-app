<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\LoginActivity;
use App\Models\User;
use App\Notifications\NewAccountCredentials;
use App\Notifications\SignInCodeNotification;
use App\Services\SignInCode;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
    $this->company = Company::create(['name' => 'Acme Co']);
    $this->manager = User::factory()->create(['company_id' => $this->company->id, 'role' => 'manager']);
    $this->manager->assignRole('manager');
});

/** Add an usher who signs in with a code, and return the code from the invite email. */
function inviteUsher($test, string $email = 'kwame@example.com'): string
{
    Notification::fake();
    $test->actingAs($test->manager)->post(route('admin.register.store'), [
        'name' => 'Kwame Asante', 'email' => $email, 'role' => 'usher', 'sign_in' => 'code',
    ])->assertRedirect(route('admin.users.index'));
    auth()->logout();

    $code = null;
    Notification::assertSentTo(User::where('email', $email)->first(), SignInCodeNotification::class, function ($n) use (&$code) {
        $code = $n->code;

        return $n->invite && $n->minutes === SignInCode::INVITE_MINUTES;
    });

    return $code;
}

it('adds an usher with an emailed code instead of a password', function () {
    $event = Event::create(['company_id' => $this->company->id, 'title' => 'Youth Camp', 'event_date' => now()]);

    $code = inviteUsher($this);

    $usher = User::where('email', 'kwame@example.com')->first();
    expect($code)->toMatch('/^\d{6}$/')
        ->and($usher->signs_in_with_code)->toBeTrue()
        ->and($usher->must_change_password)->toBeFalse()
        ->and($usher->hasRole('usher'))->toBeTrue()
        ->and($usher->events()->pluck('events.id')->all())->toBe([$event->id])
        ->and($usher->sign_in_code)->not->toBe($code);
    Notification::assertNotSentTo($usher, NewAccountCredentials::class);
});

it('signs the usher in with the code, once', function () {
    $code = inviteUsher($this);

    $this->get(route('login.code', ['email' => 'kwame@example.com']))->assertOk()->assertSee('Code from the email');
    $this->post(route('login.code.store'), ['email' => 'kwame@example.com', 'code' => substr($code, 0, 3).' '.substr($code, 3)])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs(User::where('email', 'kwame@example.com')->first());
    expect(LoginActivity::where('email', 'kwame@example.com')->where('successful', true)->count())->toBe(1);

    auth()->logout();
    $this->post(route('login.code.store'), ['email' => 'kwame@example.com', 'code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('stops a code after five wrong tries', function () {
    $code = inviteUsher($this);
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach (range(1, SignInCode::MAX_ATTEMPTS) as $try) {
        $this->post(route('login.code.store'), ['email' => 'kwame@example.com', 'code' => $wrong])->assertSessionHasErrors('code');
    }
    $this->post(route('login.code.store'), ['email' => 'kwame@example.com', 'code' => $code])->assertSessionHasErrors('code');

    $this->assertGuest();
    expect(LoginActivity::where('email', 'kwame@example.com')->where('successful', false)->count())->toBe(SignInCode::MAX_ATTEMPTS);
});

it('rejects an expired code', function () {
    $code = inviteUsher($this);
    $this->travel(8)->days();

    $this->post(route('login.code.store'), ['email' => 'kwame@example.com', 'code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('emails a new short code on request without saying whether the account exists', function () {
    inviteUsher($this);
    Notification::fake();

    $this->post(route('login.code.send'), ['email' => 'kwame@example.com'])
        ->assertRedirect(route('login.code'))->assertSessionHas('status');
    $this->post(route('login.code.send'), ['email' => 'nobody@example.com'])
        ->assertRedirect(route('login.code'))->assertSessionHas('status');

    $usher = User::where('email', 'kwame@example.com')->first();
    Notification::assertSentTo($usher, SignInCodeNotification::class, fn ($n) => ! $n->invite && $n->minutes === SignInCode::LOGIN_MINUTES);
    Notification::assertCount(1);
    expect($usher->fresh()->sign_in_code_expires_at->diffInMinutes(now(), true))->toBeLessThanOrEqual(SignInCode::LOGIN_MINUTES);
});

it('does not send codes to people who sign in with a password', function () {
    Notification::fake();

    $this->post(route('login.code.send'), ['email' => $this->manager->email])->assertSessionHas('status');

    Notification::assertNothingSent();
});

it('limits how many codes one email can ask for', function () {
    inviteUsher($this);
    Notification::fake();

    foreach (range(1, 3) as $request) {
        $this->post(route('login.code.send'), ['email' => 'kwame@example.com'])->assertSessionHasNoErrors();
    }
    $this->post(route('login.code.send'), ['email' => 'kwame@example.com'])->assertSessionHasErrors('email');

    Notification::assertSentTimes(SignInCodeNotification::class, 3);
});

it('keeps managers on passwords', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');

    $this->actingAs($admin)->post(route('admin.register.store'), [
        'name' => 'Ama Mensah', 'email' => 'ama@example.com', 'role' => 'manager', 'company_id' => $this->company->id, 'sign_in' => 'code',
    ])->assertSessionHasErrors('sign_in');

    expect(User::where('email', 'ama@example.com')->exists())->toBeFalse();
});

it('lets a manager email a code usher a new code', function () {
    inviteUsher($this);
    $usher = User::where('email', 'kwame@example.com')->first();
    Notification::fake();

    $this->actingAs($this->manager)->get(route('admin.users.edit', $usher))->assertSee('Email a new code');
    $this->post(route('admin.users.sign-in-code', $usher))->assertSessionHas('success');

    Notification::assertSentTo($usher, SignInCodeNotification::class, fn ($n) => $n->invite);
});

it('lets a code usher open reports for their events', function () {
    $event = Event::create(['company_id' => $this->company->id, 'title' => 'Youth Camp', 'event_date' => now()]);
    $code = inviteUsher($this);

    $this->post(route('login.code.store'), ['email' => 'kwame@example.com', 'code' => $code]);

    $this->get(route('reports.event', $event))->assertOk();
});
