<?php

use App\Livewire\AttendanceSearch;
use App\Livewire\StaffCheckInStats;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function staffCheckInFixture(): array
{
    $company = Company::create(['name' => 'Staff Co']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now(), 'end_date' => now()->addDay(), 'day' => 1]);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $staff = Participant::create([
        'company_id' => $company->id, 'name' => 'Kofi Staff', 'department' => 'Ushering',
        'category' => 'Staff', 'is_support_staff' => true, 'staff_code' => 'STF-000001', 'staff_qr_token' => str_repeat('a', 48),
    ]);
    $event->registrations()->create(['participant_id' => $staff->id, 'status' => 'confirmed']);
    $attendee = Participant::create(['company_id' => $company->id, 'name' => 'Ama Attendee', 'category' => 'Member']);
    $event->registrations()->create(['participant_id' => $attendee->id, 'status' => 'confirmed']);

    return [$company, $event, $manager, $staff, $attendee];
}

it('keeps support staff off the attendees list and its export', function () {
    [, $event, $manager, $staff, $attendee] = staffCheckInFixture();

    $this->actingAs($manager)
        ->get(route('events.registrations.index', $event))
        ->assertOk()
        ->assertSee('Ama Attendee')
        ->assertDontSee('Kofi Staff');

    $response = $this->actingAs($manager)->get(route('events.registrations.export', $event));
    $response->assertOk();
    expect($response->streamedContent())->toContain('Ama Attendee')->not->toContain('Kofi Staff');
});

it('keeps support staff out of the attendance roster, stats, and report', function () {
    [, $event, $manager, $staff, $attendee] = staffCheckInFixture();

    $this->actingAs($manager)
        ->get(route('events.attendance', $event))
        ->assertOk()
        ->assertSee('1'); // totalMembers only counts the one real attendee

    expect($event->confirmedParticipants()->pluck('participants.id')->all())->toBe([$attendee->id])
        ->and($event->attendanceEligibleParticipants()->pluck('participants.id')->all())->toBe([$attendee->id])
        ->and($event->confirmedStaff()->pluck('participants.id')->all())->toBe([$staff->id]);

    $this->actingAs($manager)
        ->get(route('reports.event', ['event' => $event, 'day' => 1]))
        ->assertOk()
        ->assertSee('Ama Attendee')
        ->assertDontSee('Kofi Staff');
});

it('shows only staff on the staff check-in page and lets a manager check them in with "Check In" wording', function () {
    [, $event, $manager, $staff, $attendee] = staffCheckInFixture();

    $this->actingAs($manager)
        ->get(route('support-staff.checkin', $event))
        ->assertOk()
        ->assertSee('Kofi Staff')
        ->assertDontSee('Ama Attendee')
        ->assertSee('Check In');

    expect($event->confirmedStaff()->pluck('participants.id')->all())->toBe([$staff->id]);
});

it('checks a staff member in from the manual list via the Livewire toggle', function () {
    [, $event, $manager, $staff] = staffCheckInFixture();

    $this->actingAs($manager);
    Livewire::test(AttendanceSearch::class, ['event' => $event, 'mode' => 'staff'])
        ->assertSee('Kofi Staff')
        ->assertSee('Area: Ushering')
        ->call('toggleAttendance', $staff->id)
        ->assertSet('attendedUserIds', [$staff->id]);

    $this->assertDatabaseHas('attendances', ['event_id' => $event->id, 'participant_id' => $staff->id, 'day' => -1]);
});

it('lets a manager edit a staff name and category from the staff check-in page', function () {
    [, $event, $manager, $staff] = staffCheckInFixture();

    $this->actingAs($manager);
    Livewire::test(AttendanceSearch::class, ['event' => $event, 'mode' => 'staff'])
        ->assertSee('Edit')
        ->call('startEditingStaff', $staff->id)
        ->assertSet('editName', 'Kofi Staff')
        ->assertSet('editCategory', 'Staff')
        ->assertSee('staff-editor-'.$staff->id, false)
        ->assertDontSee('min-w-72', false)
        ->set('editName', 'Kofi Mensah')
        ->set('editCategory', 'Lead Usher')
        ->call('saveStaff')
        ->assertHasNoErrors()
        ->assertDispatched('notify', message: 'Staff details updated successfully.', type: 'success')
        ->assertSee('Kofi Mensah')
        ->assertSee('Lead Usher');

    expect($staff->fresh())
        ->name->toBe('Kofi Mensah')
        ->category->toBe('Lead Usher');
});

it('does not allow the staff editor to modify someone outside the event staff roster', function () {
    [, $event, $manager, , $attendee] = staffCheckInFixture();

    $this->actingAs($manager);
    expect(fn () => Livewire::test(AttendanceSearch::class, ['event' => $event, 'mode' => 'staff'])
        ->call('startEditingStaff', $attendee->id))
        ->toThrow(ModelNotFoundException::class);
});

it('notifies through the shared toast bubble instead of a silent session flash', function () {
    [, $event, $manager, $staff] = staffCheckInFixture();
    $event->update(['cancelled_at' => now()]);

    $this->actingAs($manager);
    Livewire::test(AttendanceSearch::class, ['event' => $event, 'mode' => 'staff'])
        ->call('toggleAttendance', $staff->id)
        ->assertDispatched('notify', message: 'This event is closed.', type: 'error');

    $event->update(['cancelled_at' => null]);
    Livewire::test(AttendanceSearch::class, ['event' => $event, 'mode' => 'staff'])
        ->call('deleteUser', $staff->id)
        ->assertDispatched('notify', message: 'Member removed successfully.', type: 'success');
});

it('checks a staff member in via their staff QR and keeps it separate from daily attendance', function () {
    [, $event, $manager, $staff] = staffCheckInFixture();

    $this->actingAs($manager)
        ->postJson(route('support-staff.checkin.scan', $event), ['registration_code' => 'ASAH-STAFF:'.$staff->staff_qr_token])
        ->assertOk()
        ->assertJson(['successful' => true]);

    $this->assertDatabaseHas('attendances', ['event_id' => $event->id, 'participant_id' => $staff->id, 'day' => -1]);

    // Scanning again reports already checked in, without creating a duplicate row.
    $this->actingAs($manager)
        ->postJson(route('support-staff.checkin.scan', $event), ['registration_code' => 'ASAH-STAFF:'.$staff->staff_qr_token])
        ->assertOk()
        ->assertJsonPath('message', 'Kofi Staff is already checked in.');

    expect(Attendance::where('event_id', $event->id)->where('participant_id', $staff->id)->count())->toBe(1);
});

it('reflects a numbered staff member scanned for day one in the staff check-in area', function () {
    [, $event, $manager, $staff] = staffCheckInFixture();
    $staff->update(['name' => 'Participant 42']);

    $this->actingAs($manager)
        ->postJson(route('events.scanner.check-in', $event), ['registration_code' => 'ASAH-STAFF:'.$staff->staff_qr_token])
        ->assertOk()
        ->assertJsonPath('successful', true);

    $this->assertDatabaseHas('attendances', ['event_id' => $event->id, 'participant_id' => $staff->id, 'day' => 1]);
    $this->assertDatabaseHas('attendances', ['event_id' => $event->id, 'participant_id' => $staff->id, 'day' => -1]);

    Livewire::test(AttendanceSearch::class, ['event' => $event, 'mode' => 'staff'])
        ->assertSet('attendedUserIds', [$staff->id]);
    Livewire::test(StaffCheckInStats::class, ['event' => $event])
        ->assertViewHas('checkedIn', 1);
});

it('rejects a normal attendee QR code at the staff scanner', function () {
    [, $event, $manager, , $attendee] = staffCheckInFixture();
    $registration = $event->registrations()->where('participant_id', $attendee->id)->firstOrFail();

    $this->actingAs($manager)
        ->postJson(route('support-staff.checkin.scan', $event), ['registration_code' => $registration->registration_code])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This code does not belong to event staff for this event.');

    $this->assertDatabaseMissing('attendances', ['event_id' => $event->id, 'participant_id' => $attendee->id]);
});

it('shows staff checked-in and not-checked-in counts on the staff report', function () {
    [, $event, $manager, $staff] = staffCheckInFixture();
    $otherStaff = Participant::create([
        'company_id' => $event->company_id, 'name' => 'Yaw Staff', 'category' => 'Staff',
        'is_support_staff' => true, 'staff_code' => 'STF-000002', 'staff_qr_token' => str_repeat('b', 48),
    ]);
    $event->registrations()->create(['participant_id' => $otherStaff->id, 'status' => 'confirmed']);
    Attendance::create(['event_id' => $event->id, 'participant_id' => $staff->id, 'day' => -1, 'status' => 'present']);

    $this->actingAs($manager)
        ->get(route('support-staff.report', $event))
        ->assertOk()
        ->assertSee('Kofi Staff')
        ->assertSee('Yaw Staff')
        ->assertSeeInOrder(['Checked in (1)', 'Not checked in (1)']);

    $csv = $this->actingAs($manager)->get(route('support-staff.report.csv', $event));
    $csv->assertOk();
    expect($csv->streamedContent())->toContain('Kofi Staff')->toContain('Yaw Staff');
});

it('prevents a manager from another company reaching a staff check-in page', function () {
    [, $event] = staffCheckInFixture();
    $otherManager = User::factory()->create(['company_id' => Company::create(['name' => 'Other'])->id, 'role' => 'manager']);
    $otherManager->assignRole('manager');

    $this->actingAs($otherManager)
        ->get(route('support-staff.checkin', $event))
        ->assertForbidden();
});
