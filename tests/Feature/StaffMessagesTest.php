<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\StaffMessage;
use App\Models\StaffMessageRecipient;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher', 'audit_head', 'audit_staff'] as $role) {
        Role::findOrCreate($role);
    }
    $this->company = Company::create(['name' => 'Message Org']);
    $this->manager = User::factory()->create(['company_id' => $this->company->id, 'role' => 'manager']);
    $this->manager->assignRole('manager');
    $this->event = Event::create(['company_id' => $this->company->id, 'title' => 'Message Conference', 'event_date' => now()]);
    $this->staff = function (string $name, string $role = 'usher', ?Company $company = null): User {
        $user = User::factory()->create(['name' => $name, 'company_id' => ($company ?? $this->company)->id, 'role' => $role]);
        $user->assignRole($role);

        return $user;
    };
});

it('sends to every usher, meal-station and audit account in the company, and no one else', function () {
    $usher = ($this->staff)('Kwame Asante');
    $mealStaff = ($this->staff)('Ama Owusu', 'audit_staff');
    $auditHead = ($this->staff)('Kofi Mensah', 'audit_head');
    $elsewhere = ($this->staff)('Yaw Boateng', 'usher', Company::create(['name' => 'Other Org']));

    $this->actingAs($this->manager)->post(route('staff-messages.store'), ['audience' => 'all', 'body' => 'Briefing at 7am in the main hall.'])
        ->assertRedirect(route('staff-messages.index'))->assertSessionHas('success', 'Message sent to 3 staff members.');

    $message = StaffMessage::sole();
    expect($message->recipients()->pluck('user_id')->sort()->values()->all())->toBe(collect([$usher->id, $mealStaff->id, $auditHead->id])->sort()->values()->all())
        ->and($message->recipients()->where('user_id', $elsewhere->id)->exists())->toBeFalse()
        ->and($message->recipients()->where('user_id', $this->manager->id)->exists())->toBeFalse();
});

it('sends to the staff on one event, or to one person', function () {
    $onEvent = ($this->staff)('Kwame Asante');
    $onEvent->events()->attach($this->event);
    $offEvent = ($this->staff)('Ama Owusu');

    $this->actingAs($this->manager)->post(route('staff-messages.store'), ['audience' => 'event', 'event_id' => $this->event->id, 'body' => 'Gate B is closed.']);
    $this->actingAs($this->manager)->post(route('staff-messages.store'), ['audience' => 'user', 'user_id' => $offEvent->id, 'body' => 'Please come to the desk.']);

    [$eventMessage, $personalMessage] = StaffMessage::orderBy('id')->get();
    expect($eventMessage->recipients()->pluck('user_id')->all())->toBe([$onEvent->id])
        ->and($personalMessage->recipients()->pluck('user_id')->all())->toBe([$offEvent->id]);
});

it('will not send to staff or events of another company', function () {
    $other = Company::create(['name' => 'Other Org']);
    $outsider = ($this->staff)('Yaw Boateng', 'usher', $other);
    $otherEvent = Event::create(['company_id' => $other->id, 'title' => 'Other Event', 'event_date' => now()]);

    $this->actingAs($this->manager)->post(route('staff-messages.store'), ['audience' => 'user', 'user_id' => $outsider->id, 'body' => 'Hello'])
        ->assertSessionHas('error');
    $this->actingAs($this->manager)->post(route('staff-messages.store'), ['audience' => 'event', 'event_id' => $otherEvent->id, 'body' => 'Hello'])
        ->assertSessionHasErrors('event_id');

    expect(StaffMessage::count())->toBe(0);
});

it('lets staff read their messages but not send or manage them', function () {
    $usher = ($this->staff)('Kwame Asante');

    $this->actingAs($usher)->get(route('staff-messages.index'))->assertForbidden();
    $this->actingAs($usher)->post(route('staff-messages.store'), ['audience' => 'all', 'body' => 'Hi'])->assertForbidden();
    $this->actingAs($usher)->get(route('dashboard'))->assertOk()->assertSee('aria-label="Messages"', false);
    $this->actingAs($this->manager)->get(route('dashboard'))->assertOk()->assertDontSee('aria-label="Messages"', false);
});

it('shows staff their messages on the bell and marks them read', function () {
    $usher = ($this->staff)('Kwame Asante');
    $this->actingAs($this->manager)->post(route('staff-messages.store'), ['audience' => 'all', 'body' => 'Briefing at 7am.']);
    $this->actingAs($this->manager)->post(route('staff-messages.store'), ['audience' => 'all', 'body' => 'Gate B is closed.', 'urgent' => '1']);

    $this->actingAs($usher)->getJson(route('staff-messages.feed'))->assertOk()
        ->assertJsonPath('unread', 2)
        ->assertJsonPath('items.0.body', 'Gate B is closed.')
        ->assertJsonPath('items.0.from', $this->manager->name)
        ->assertJsonCount(1, 'urgent');

    $urgent = StaffMessage::where('urgent', true)->sole();
    $this->actingAs($usher)->postJson(route('staff-messages.read'), ['ids' => [$urgent->id]])->assertJsonPath('unread', 1);
    $this->actingAs($usher)->getJson(route('staff-messages.feed'))->assertJsonCount(0, 'urgent');

    $this->actingAs($usher)->postJson(route('staff-messages.read'))->assertJsonPath('unread', 0);
});

it('shows the sender who has read a message', function () {
    $reader = ($this->staff)('Kwame Asante');
    ($this->staff)('Ama Owusu');
    $this->actingAs($this->manager)->post(route('staff-messages.store'), ['audience' => 'all', 'body' => 'Briefing at 7am.']);
    $message = StaffMessage::sole();
    StaffMessageRecipient::where('user_id', $reader->id)->update(['read_at' => now()]);

    $this->actingAs($this->manager)->get(route('staff-messages.index'))->assertOk()->assertSee('Seen by 1 of 2');
    $this->actingAs($this->manager)->get(route('staff-messages.show', $message))->assertOk()
        ->assertSeeInOrder(['Ama Owusu', 'Not read', 'Kwame Asante', 'Read']);
});

it('keeps another company\'s messages out of reach, and lets the sender delete their own', function () {
    ($this->staff)('Kwame Asante');
    $this->actingAs($this->manager)->post(route('staff-messages.store'), ['audience' => 'all', 'body' => 'Briefing at 7am.']);
    $message = StaffMessage::sole();

    $otherManager = User::factory()->create(['company_id' => Company::create(['name' => 'Other Org'])->id, 'role' => 'manager']);
    $otherManager->assignRole('manager');
    $this->actingAs($otherManager)->get(route('staff-messages.show', $message))->assertNotFound();
    $this->actingAs($otherManager)->delete(route('staff-messages.destroy', $message))->assertNotFound();

    $this->actingAs($this->manager)->delete(route('staff-messages.destroy', $message))->assertRedirect();
    expect(StaffMessage::count())->toBe(0)->and(StaffMessageRecipient::count())->toBe(0);
});

it('lets a platform admin message a chosen company\'s staff', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');
    $usher = ($this->staff)('Kwame Asante');

    $this->actingAs($admin)->get(route('staff-messages.index', ['company_id' => $this->company->id]))->assertOk()->assertSee('All staff (1)');
    $this->actingAs($admin)->post(route('staff-messages.store', ['company_id' => $this->company->id]), ['audience' => 'all', 'body' => 'Hello team'])
        ->assertRedirect(route('staff-messages.index', ['company_id' => $this->company->id]));

    expect(StaffMessage::sole()->recipients()->pluck('user_id')->all())->toBe([$usher->id]);
});
