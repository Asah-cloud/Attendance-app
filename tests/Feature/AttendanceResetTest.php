<?php

use App\Models\Attendance;
use App\Models\AttendanceReset;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\AttendanceWasReset;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Notification::fake();
    Role::findOrCreate('manager');
    Role::findOrCreate('usher');
    $this->company = Company::create(['name' => 'Acme Co']);
    $this->manager = User::factory()->create(['company_id' => $this->company->id, 'role' => 'manager']);
    $this->manager->assignRole('manager');
    $this->event = Event::create(['company_id' => $this->company->id, 'title' => 'Youth Camp', 'event_date' => now()->subDay(), 'end_date' => now()->addDay(), 'status' => 'active']);
    foreach (['Kwame Asante', 'Ama Mensah'] as $name) {
        $person = Participant::create(['company_id' => $this->company->id, 'name' => $name]);
        EventRegistration::create(['event_id' => $this->event->id, 'participant_id' => $person->id, 'status' => EventRegistration::STATUS_CONFIRMED]);
        foreach ([1, 2] as $day) {
            Attendance::create(['event_id' => $this->event->id, 'participant_id' => $person->id, 'day' => $day, 'session' => 1, 'status' => 'present', 'marked_by' => $this->manager->id]);
        }
    }
});

function resetAttendance($test, array $data = [])
{
    return $test->actingAs($test->manager)->from(route('events.attendance', $test->event))
        ->post(route('events.attendance.reset', $test->event), $data + ['day' => 'all', 'confirm_title' => 'Youth Camp', 'password' => 'password']);
}

it('shows the reset button to managers', function () {
    $this->actingAs($this->manager)->get(route('events.attendance', $this->event))
        ->assertOk()->assertSee('Reset attendance')->assertSee('All days (4 check-ins)');
});

it('resets every check-in and tells the managers', function () {
    resetAttendance($this)->assertSessionHasNoErrors()->assertSessionHas('success');

    expect(Attendance::where('event_id', $this->event->id)->count())->toBe(0);
    $reset = AttendanceReset::first();
    expect($reset->removed_count)->toBe(4)->and($reset->day)->toBeNull()->and($reset->user_id)->toBe($this->manager->id);
    Notification::assertSentTo($this->manager, AttendanceWasReset::class);
});

it('resets one day only', function () {
    resetAttendance($this, ['day' => '2'])->assertSessionHasNoErrors();

    expect(Attendance::where('event_id', $this->event->id)->pluck('day')->unique()->values()->all())->toBe([1]);
});

it('needs the event title and the right password', function () {
    resetAttendance($this, ['confirm_title' => 'Youth'])->assertSessionHasErrors('confirm_title');
    resetAttendance($this, ['password' => 'wrong-password'])->assertSessionHasErrors('password');
    resetAttendance($this, ['password' => ''])->assertSessionHasErrors('password');

    expect(Attendance::where('event_id', $this->event->id)->count())->toBe(4);
    Notification::assertNothingSent();
});

it('puts the check-ins back on undo, keeping ones taken again since', function () {
    resetAttendance($this);
    $kwame = Participant::where('name', 'Kwame Asante')->first();
    Attendance::create(['event_id' => $this->event->id, 'participant_id' => $kwame->id, 'day' => 1, 'session' => 1, 'status' => 'present']);

    $this->post(route('events.attendance.reset.undo', [$this->event, AttendanceReset::first()]))->assertSessionHas('success');

    expect(Attendance::where('event_id', $this->event->id)->count())->toBe(4)
        ->and(AttendanceReset::first()->undone_at)->not->toBeNull();
    Notification::assertSentTo($this->manager, AttendanceWasReset::class, fn ($n) => $n->undone);

    $this->post(route('events.attendance.reset.undo', [$this->event, AttendanceReset::first()]))->assertSessionHas('error');
    expect(Attendance::where('event_id', $this->event->id)->count())->toBe(4);
});

it('is locked once the event has closed', function () {
    $this->event->update(['event_date' => now()->subDays(5), 'end_date' => now()->subDays(3)]);

    expect(resetAttendance($this)->status())->toBeIn([302, 403]);
    expect(Attendance::where('event_id', $this->event->id)->count())->toBe(4)
        ->and(AttendanceReset::count())->toBe(0);
});

it('is not open to ushers or other companies', function () {
    $usher = User::factory()->create(['company_id' => $this->company->id, 'role' => 'usher']);
    $usher->assignRole('usher');
    $other = User::factory()->create(['company_id' => Company::create(['name' => 'Other Co'])->id, 'role' => 'manager']);
    $other->assignRole('manager');

    foreach ([$usher, $other] as $user) {
        $this->actingAs($user)->post(route('events.attendance.reset', $this->event), ['day' => 'all', 'confirm_title' => 'Youth Camp', 'password' => 'password'])->assertForbidden();
    }
    expect(Attendance::where('event_id', $this->event->id)->count())->toBe(4);
});
