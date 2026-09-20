<?php

use App\Livewire\AddWalkInModal;
use App\Livewire\AttendanceSearch;
use App\Livewire\AttendanceStats;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Notification::fake();
    Role::findOrCreate('manager');
});

it('refreshes attendance totals after attendance and walk-in changes', function () {
    $company = Company::create(['name' => 'Live Stats Company']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $event = Event::create(['company_id' => $company->id, 'title' => 'Live Event', 'event_date' => now()]);
    $participant = Participant::create(['company_id' => $company->id, 'name' => 'First Guest']);
    EventRegistration::create([
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'status' => EventRegistration::STATUS_CONFIRMED,
    ]);

    $this->actingAs($manager);

    Livewire::test(AttendanceStats::class, ['event' => $event, 'day' => 1])
        ->assertSee('Eligible attendees')
        ->assertSeeHtml('>1<');

    Livewire::test(AttendanceSearch::class, ['event' => $event, 'day' => 1])
        ->call('toggleAttendance', $participant->id)
        ->assertDispatched('attendanceStatsChanged');

    Livewire::test(AttendanceStats::class, ['event' => $event, 'day' => 1])
        ->assertSeeHtml('>1<');

    Livewire::test(AddWalkInModal::class, ['event' => $event])
        ->set('name', 'Walk In Guest')
        ->call('registerWalkIn')
        ->assertDispatched('attendanceStatsChanged');

    expect($event->attendanceEligibleParticipants()->count())->toBe(2);
});

it('counts checked-in numbered participant staff throughout the event', function () {
    $company = Company::create(['name' => 'Persistent Participant Staff Company']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $event = Event::create([
        'company_id' => $company->id,
        'title' => 'Three Day Event',
        'event_date' => now(),
        'end_date' => now()->addDays(2),
        'day' => 1,
    ]);

    $attendee = Participant::create(['company_id' => $company->id, 'name' => 'Regular Guest']);
    EventRegistration::create(['event_id' => $event->id, 'participant_id' => $attendee->id, 'status' => EventRegistration::STATUS_CONFIRMED]);
    Attendance::create(['event_id' => $event->id, 'participant_id' => $attendee->id, 'day' => 2, 'status' => 'present']);

    foreach (['Participant 1', 'participant 2', 'Participant Coordinator'] as $name) {
        $staff = Participant::create(['company_id' => $company->id, 'name' => $name, 'is_support_staff' => true]);
        EventRegistration::create(['event_id' => $event->id, 'participant_id' => $staff->id, 'status' => EventRegistration::STATUS_CONFIRMED]);
        Attendance::create(['event_id' => $event->id, 'participant_id' => $staff->id, 'day' => -1, 'status' => 'present']);
    }

    $this->actingAs($manager);

    Livewire::test(AttendanceStats::class, ['event' => $event, 'day' => 1])
        ->assertViewHas('participantStaffCount', 2)
        ->assertViewHas('presentCount', 2)
        ->assertSee('Participant staff present');

    Livewire::test(AttendanceStats::class, ['event' => $event, 'day' => 2])
        ->assertViewHas('participantStaffCount', 2)
        ->assertViewHas('presentCount', 3);
});
