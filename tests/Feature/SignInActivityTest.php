<?php

use App\Models\Company;
use App\Models\LoginActivity;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
    $this->company = Company::create(['name' => 'The Apostolic Church-Ghana HQ']);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->admin->assignRole('admin');
    $this->usher = User::factory()->create(['name' => 'Kofi Mensah', 'email' => 'kofi@example.com', 'company_id' => $this->company->id, 'role' => 'usher']);
    $this->usher->assignRole('usher');
});

it('records a sign-in once, with the role, organisation, device and address', function () {
    $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1')
        ->post(route('login'), ['email' => 'kofi@example.com', 'password' => 'password']);

    $activity = LoginActivity::sole();
    expect($activity->successful)->toBeTrue()
        ->and($activity->user_id)->toBe($this->usher->id)
        ->and($activity->company_id)->toBe($this->company->id)
        ->and($activity->roleLabel())->toBe('Usher')
        ->and($activity->deviceLabel())->toBe('Safari on iPhone')
        ->and($activity->ip_address)->toBe('127.0.0.1')
        ->and($activity->summary())->toBe('Kofi Mensah (Usher · The Apostolic Church-Ghana HQ) signed in');
});

it('raises an alert on the fifth failed attempt for one email within ten minutes', function () {
    foreach (range(1, 4) as $attempt) {
        $this->post(route('login'), ['email' => 'Kofi@example.com', 'password' => 'wrong']);
    }
    expect(LoginActivity::where('successful', false)->count())->toBe(4)
        ->and(LoginActivity::where('alert', true)->count())->toBe(0);

    $this->travel(1)->minutes();
    $this->post(route('login'), ['email' => 'kofi@example.com', 'password' => 'wrong']);

    $alert = LoginActivity::where('alert', true)->sole();
    expect($alert->email)->toBe('kofi@example.com')
        ->and($alert->summary())->toBe('5 failed sign-in attempts for kofi@example.com in 10 minutes');
});

it('feeds the admin bell with others\' sign-ins and alerts, an unread count, and new entries for pop-ups', function () {
    $own = LoginActivity::create(['user_id' => $this->admin->id, 'email' => $this->admin->email, 'roles' => 'admin', 'successful' => true]);
    $first = LoginActivity::create(['user_id' => $this->usher->id, 'company_id' => $this->company->id, 'email' => 'kofi@example.com', 'roles' => 'usher', 'successful' => true]);
    LoginActivity::create(['email' => 'nobody@example.com', 'successful' => false]);

    $this->actingAs($this->admin);
    $feed = $this->getJson(route('admin.sign-ins.feed'))->assertOk()->json();
    expect($feed['unread'])->toBe(1)
        ->and(collect($feed['items'])->pluck('id')->all())->toBe([$first->id])
        ->and($feed['new'])->toBe([]);

    $alert = LoginActivity::create(['email' => 'kofi@example.com', 'successful' => false, 'alert' => true]);
    $feed = $this->getJson(route('admin.sign-ins.feed', ['after' => $feed['last_id']]))->json();
    expect(collect($feed['new'])->pluck('id')->all())->toBe([$alert->id])
        ->and($feed['new'][0]['alert'])->toBeTrue()
        ->and($feed['unread'])->toBe(2);

    $this->travel(1)->seconds();
    $this->postJson(route('admin.sign-ins.seen'))->assertOk();
    expect($this->getJson(route('admin.sign-ins.feed'))->json('unread'))->toBe(0);
    expect($own->exists)->toBeTrue();
});

it('lists and filters every sign-in on the admin page', function () {
    LoginActivity::create(['user_id' => $this->usher->id, 'company_id' => $this->company->id, 'email' => 'kofi@example.com', 'roles' => 'usher', 'successful' => true, 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0 Safari/537.36']);
    LoginActivity::create(['email' => 'guess@example.com', 'successful' => false]);

    $this->actingAs($this->admin)->get(route('admin.sign-ins.index'))
        ->assertOk()->assertSee('Kofi Mensah')->assertSee('Chrome on Windows')->assertSee('guess@example.com');
    $this->get(route('admin.sign-ins.index', ['status' => 'failed']))
        ->assertOk()->assertSee('guess@example.com')->assertDontSee('Kofi Mensah');
    $this->get(route('admin.sign-ins.index', ['company' => $this->company->id, 'search' => 'kofi']))
        ->assertOk()->assertSee('Kofi Mensah')->assertDontSee('guess@example.com');
});

it('shows the bell to platform admins only', function () {
    $this->actingAs($this->admin)->get(route('profile.edit'))->assertSee('aria-label="Sign-in activity"', false);

    $manager = User::factory()->create(['company_id' => $this->company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $this->actingAs($manager)->get(route('profile.edit'))->assertDontSee('aria-label="Sign-in activity"', false);
    $this->getJson(route('admin.sign-ins.feed'))->assertForbidden();
    $this->get(route('admin.sign-ins.index'))->assertForbidden();
});

it('clears sign-in records older than ninety days', function () {
    $old = LoginActivity::create(['email' => 'old@example.com', 'successful' => true]);
    $old->forceFill(['created_at' => now()->subDays(91)])->save();
    $recent = LoginActivity::create(['email' => 'new@example.com', 'successful' => true]);

    $this->artisan('model:prune', ['--model' => [LoginActivity::class]])->assertSuccessful();

    expect(LoginActivity::pluck('id')->all())->toBe([$recent->id]);
});
