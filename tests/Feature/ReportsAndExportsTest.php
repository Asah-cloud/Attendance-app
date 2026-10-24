<?php

use App\Exports\AreaAttendanceSummaryExport;
use App\Exports\AttendanceExport;
use App\Exports\AttendanceWorkbook;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use App\Services\AttendanceGrid;
use App\Services\AttendanceReportData;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function reportsManager(Company $company): User
{
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    return $manager;
}

function reportsEventWithAttendee(Company $company): Event
{
    $event = Event::create(['company_id' => $company->id, 'title' => 'Reported Event', 'event_date' => now()]);
    unlockAllEventFeatures($event);
    $participant = Participant::create(['company_id' => $company->id, 'name' => 'Attendee']);
    $event->registrations()->create(['participant_id' => $participant->id, 'status' => 'confirmed']);

    return $event;
}

it('sends the old summary page to the all-days report', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = reportsManager($company);
    $event = reportsEventWithAttendee($company);

    $this->actingAs($manager)
        ->get(route('reports.summary', $event))
        ->assertRedirect(route('reports.event', ['event' => $event, 'day' => 'all']));
});

it('prevents a manager from viewing the summary report for another company event', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $otherCompany = Company::create(['name' => 'Other Co']);
    $manager = reportsManager($company);
    $event = reportsEventWithAttendee($otherCompany);

    $this->actingAs($manager)
        ->get(route('reports.summary', $event))
        ->assertForbidden();
});

it('allows a manager to download the summary report export', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = reportsManager($company);
    $event = reportsEventWithAttendee($company);

    $response = $this->actingAs($manager)->get(route('reports.summary.export', $event));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('.xlsx');
});

it('allows a manager to export event attendance as excel', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = reportsManager($company);
    $event = reportsEventWithAttendee($company);

    $response = $this->actingAs($manager)->get(route('reports.excel', ['event' => $event, 'day' => 1]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('.xlsx');
});

it('allows a manager to export event attendance as pdf', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = reportsManager($company);
    $event = reportsEventWithAttendee($company);

    $response = $this->actingAs($manager)->get(route('reports.pdf', ['event' => $event, 'day' => 1]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain('.pdf');
});

it('allows a manager to export the full summary as pdf', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = reportsManager($company);
    $event = reportsEventWithAttendee($company);

    $response = $this->actingAs($manager)->get(route('reports.summary.pdf', $event));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

it('prevents a manager from exporting another company event as pdf', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $otherCompany = Company::create(['name' => 'Other Co']);
    $manager = reportsManager($company);
    $event = reportsEventWithAttendee($otherCompany);

    $this->actingAs($manager)
        ->get(route('reports.pdf', ['event' => $event, 'day' => 1]))
        ->assertForbidden();
});

it('allows a manager to export event attendance as csv', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = reportsManager($company);
    $event = reportsEventWithAttendee($company);

    $response = $this->actingAs($manager)->get(route('reports.csv', ['event' => $event, 'day' => 1]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('.csv');
});

it('downloads a grouped area attendance summary for the selected period', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = reportsManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Area Event', 'event_date' => now()]);
    unlockAllEventFeatures($event);
    foreach (['Kumasi Area', 'Kumasi Area', 'Accra Area'] as $index => $area) {
        $participant = Participant::create(['company_id' => $company->id, 'name' => 'Guest '.$index, 'room_group' => $area]);
        $event->registrations()->create(['participant_id' => $participant->id, 'status' => 'confirmed']);
        Attendance::create(['event_id' => $event->id, 'participant_id' => $participant->id, 'day' => 1, 'status' => 'present']);
    }

    $response = $this->actingAs($manager)->get(route('reports.area-summary', ['event' => $event, 'day' => 1]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('Area_Attendance_')->toContain('.xlsx');
});

it('downloads the detailed attendee list for a single area, including unspecified areas', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = reportsManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Area Event', 'event_date' => now()]);
    unlockAllEventFeatures($event);

    $kumasi = Participant::create(['company_id' => $company->id, 'name' => 'Kumasi Guest', 'room_group' => 'Kumasi Area']);
    $noArea = Participant::create(['company_id' => $company->id, 'name' => 'No Area Guest']);
    foreach ([$kumasi, $noArea] as $participant) {
        $event->registrations()->create(['participant_id' => $participant->id, 'status' => 'confirmed']);
        Attendance::create(['event_id' => $event->id, 'participant_id' => $participant->id, 'day' => 1, 'status' => 'present']);
    }

    $this->actingAs($manager)
        ->get(route('reports.area-detail', ['event' => $event, 'area' => 'Kumasi Area', 'day' => 1]))
        ->assertOk()
        ->assertHeader('Content-Disposition');

    $this->actingAs($manager)
        ->get(route('reports.area-detail', ['event' => $event, 'area' => 'Area not specified', 'day' => 1]))
        ->assertOk()
        ->assertHeader('Content-Disposition');
});

it('prevents a manager from exporting another company event attendance', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $otherCompany = Company::create(['name' => 'Other Co']);
    $manager = reportsManager($company);
    $event = reportsEventWithAttendee($otherCompany);

    $this->actingAs($manager)
        ->get(route('reports.csv', ['event' => $event, 'day' => 1]))
        ->assertForbidden();
});

it('shows category and gender breakdowns and supports filtering the attendance report', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = reportsManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Reported Event', 'event_date' => now()]);
    unlockAllEventFeatures($event);

    $male = Participant::create(['company_id' => $company->id, 'name' => 'Male Guest', 'phone' => '0201111111', 'category' => 'Guest', 'gender' => 'Male', 'room_group' => 'Kumasi Area']);
    $female = Participant::create(['company_id' => $company->id, 'name' => 'Female Member', 'phone' => '0202222222', 'category' => 'Member', 'gender' => 'Female', 'room_group' => 'Kumasi Area']);
    $event->registrations()->create(['participant_id' => $male->id, 'status' => 'confirmed']);
    $event->registrations()->create(['participant_id' => $female->id, 'status' => 'confirmed']);
    Attendance::create(['event_id' => $event->id, 'participant_id' => $male->id, 'day' => 1, 'status' => 'present']);
    Attendance::create(['event_id' => $event->id, 'participant_id' => $female->id, 'day' => 1, 'status' => 'present']);

    $this->actingAs($manager)
        ->get(route('reports.event', ['event' => $event, 'day' => 1, 'view' => 'detailed']))
        ->assertOk()
        ->assertSee('Male Guest')
        ->assertSee('Female Member')
        ->assertSee('By category')
        ->assertSee('By gender');

    $this->actingAs($manager)
        ->get(route('reports.event', ['event' => $event, 'day' => 1, 'view' => 'detailed', 'gender' => 'Male']))
        ->assertOk()
        ->assertSee('Male Guest')
        ->assertDontSee('Female Member')
        ->assertViewHas('grid', fn ($grid) => $grid['totalExpected'] === 1 && $grid['rows']->pluck('area')->all() === ['Kumasi Area']);

    $this->actingAs($manager)
        ->get(route('reports.event', ['event' => $event, 'day' => 1, 'view' => 'detailed', 'category' => 'Member']))
        ->assertOk()
        ->assertSee('Female Member')
        ->assertDontSee('Male Guest');

    // Filters only apply on the Detailed view; Simple always shows the whole event.
    $this->actingAs($manager)
        ->get(route('reports.event', ['event' => $event, 'day' => 1, 'gender' => 'Male']))
        ->assertOk()
        ->assertViewHas('grid', fn ($grid) => $grid['totalExpected'] === 2);

    $this->actingAs($manager)
        ->get(route('reports.event', ['event' => $event, 'day' => 'all', 'view' => 'detailed', 'area' => 'Kumasi Area']))
        ->assertOk()
        ->assertSee('By area')
        ->assertSee('Kumasi Area')
        ->assertSee('0201111111')
        ->assertSee('Male Guest')
        ->assertSee('Female Member');
});

it('rejects an out of range day for the attendance export', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = reportsManager($company);
    $event = reportsEventWithAttendee($company);

    $this->actingAs($manager)
        ->get(route('reports.csv', ['event' => $event, 'day' => 99]))
        ->assertSessionHasErrors('day');
});

it('uses the same numbered staff and unique-person counts in reports and area exports', function () {
    $company = Company::create(['name' => 'Area Co']);
    $manager = reportsManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Two Day Event', 'event_date' => now()->subDay(), 'end_date' => now()]);
    unlockAllEventFeatures($event);
    $guest = Participant::create(['company_id' => $company->id, 'name' => 'Guest', 'room_group' => 'Kumasi']);
    $numbered = Participant::create(['company_id' => $company->id, 'name' => 'Participant 7', 'is_support_staff' => true, 'department' => 'Accra']);
    $otherStaff = Participant::create(['company_id' => $company->id, 'name' => 'Usher', 'is_support_staff' => true, 'department' => 'Accra']);
    foreach ([$guest, $numbered, $otherStaff] as $person) {
        $event->registrations()->create(['participant_id' => $person->id, 'status' => 'confirmed']);
    }
    Attendance::create(['event_id' => $event->id, 'participant_id' => $guest->id, 'day' => 1]);
    Attendance::create(['event_id' => $event->id, 'participant_id' => $guest->id, 'day' => 2]);
    Attendance::create(['event_id' => $event->id, 'participant_id' => $numbered->id, 'day' => -1]);
    Attendance::create(['event_id' => $event->id, 'participant_id' => $otherStaff->id, 'day' => -1]);

    $report = app(AttendanceReportData::class)->forPeriod($event, 'all');
    expect($report['presentUsers']->pluck('name')->all())->toBe(['Guest', 'Participant 7'])
        ->and($report['totalExpected'])->toBe(2)
        ->and($report['absentUsers'])->toBeEmpty();
    expect(app(AttendanceReportData::class)->forPeriod($event, 2)['presentUsers']->pluck('name')->all())->toBe(['Guest', 'Participant 7']);
    expect((new AreaAttendanceSummaryExport($event, 'all'))->collection()->pluck('present', 'area')->all())
        ->toBe(['Kumasi' => 1, 'Accra' => 1]);
    expect((new AttendanceExport($event, 'all'))->collection()->count())->toBe(2);

    $this->actingAs($manager)
        ->get(route('reports.event', ['event' => $event, 'day' => 'all', 'view' => 'detailed']))
        ->assertOk()
        ->assertSee('Participant 7')
        ->assertSee('Staff check-in (all event days)')
        ->assertDontSee('Usher');
});

it('gives every event day its own column so multi-day attendance is not squeezed into one', function () {
    $company = Company::create(['name' => 'Grid Co']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Two Day Event', 'event_date' => now()->subDays(2), 'end_date' => now()->subDay()]);
    unlockAllEventFeatures($event);
    $both = Participant::create(['company_id' => $company->id, 'name' => 'Both Days', 'room_group' => 'Accra']);
    $second = Participant::create(['company_id' => $company->id, 'name' => 'Second Day', 'room_group' => 'Accra']);
    $never = Participant::create(['company_id' => $company->id, 'name' => 'Never Came', 'room_group' => 'Kumasi']);
    foreach ([$both, $second, $never] as $person) {
        $event->registrations()->create(['participant_id' => $person->id, 'status' => 'confirmed']);
    }
    Attendance::create(['event_id' => $event->id, 'participant_id' => $both->id, 'day' => 1, 'method' => 'self']);
    Attendance::create(['event_id' => $event->id, 'participant_id' => $both->id, 'day' => 2]);
    Attendance::create(['event_id' => $event->id, 'participant_id' => $second->id, 'day' => 2]);

    $export = new AttendanceExport($event, 'all');
    expect($export->headings())->toContain('Day 1', 'Day 2', 'Days attended');
    $rows = $export->collection()->map(fn ($person) => array_combine($export->headings(), $export->map($person)))->keyBy('Name');

    expect($rows['Both Days']['Day 1'])->not->toBe('—')
        ->and($rows['Both Days']['Day 2'])->not->toBe('—')
        ->and($rows['Both Days']['Days attended'])->toBe('2 of 2')
        ->and($rows['Both Days']['Check-in method'])->toBe('Self & by staff')
        ->and($rows['Second Day']['Day 1'])->toBe('—')
        ->and($rows['Second Day']['Days attended'])->toBe('1 of 2')
        ->and($rows->has('Never Came'))->toBeFalse();

    expect((new AreaAttendanceSummaryExport($event, 'all'))->collection()->firstWhere('area', 'Accra'))
        ->toMatchArray(['slots' => [1, 2], 'present' => 2]);

    $sheets = (new AttendanceWorkbook($event, 'all'))->sheets();
    expect(array_map(fn ($sheet) => $sheet->title(), $sheets))->toBe(['Summary', 'Came at least once', 'Never came'])
        ->and($sheets[2]->array())->toHaveCount(1)
        ->and($sheets[2]->array()[0][1])->toBe('Never Came');
});

it('leaves days that have not happened yet out of the columns instead of marking people absent', function () {
    $company = Company::create(['name' => 'Grid Co']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Running Event', 'event_date' => now(), 'end_date' => now()->addDays(2)]);
    unlockAllEventFeatures($event);
    $person = Participant::create(['company_id' => $company->id, 'name' => 'Early Bird']);
    $event->registrations()->create(['participant_id' => $person->id, 'status' => 'confirmed']);
    Attendance::create(['event_id' => $event->id, 'participant_id' => $person->id, 'day' => 1]);

    $grid = app(AttendanceGrid::class)->build($event, 'all');

    expect($grid['slots']->pluck('label')->all())->toBe(['Day 1'])
        ->and($grid['rows']->first()->daysAttended)->toBe(1)
        ->and($grid['turnout'])->toBe(100);
});

it('builds the styled excel workbook and both pdfs for a multi-day event', function () {
    $company = Company::create(['name' => 'Grid Co']);
    $manager = reportsManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => "TAC Minister's Orientation", 'event_date' => now()->subDay(), 'end_date' => now()]);
    unlockAllEventFeatures($event);
    $person = Participant::create(['company_id' => $company->id, 'name' => 'Attendee']);
    $event->registrations()->create(['participant_id' => $person->id, 'status' => 'confirmed']);
    Attendance::create(['event_id' => $event->id, 'participant_id' => $person->id, 'day' => 1]);

    $excel = $this->actingAs($manager)->get(route('reports.excel', ['event' => $event, 'day' => 'all']))->assertOk();
    expect($excel->headers->get('Content-Disposition'))->toContain('TAC_Ministers_Orientation_Attendance_All_days.xlsx');
    $this->actingAs($manager)->get(route('reports.pdf', ['event' => $event, 'day' => 'all']))->assertOk();
    $this->actingAs($manager)->get(route('reports.summary.pdf', $event))->assertOk();
    $this->actingAs($manager)->get(route('reports.summary.export', $event))->assertOk();
    $this->actingAs($manager)->get(route('reports.area-summary', ['event' => $event, 'day' => 'all']))->assertOk();
});

function fourDayEventWithTwoCheckInDays(): array
{
    $company = Company::create(['name' => 'Orientation Co']);
    $manager = reportsManager($company);
    // Four event days (like an orientation that ran 19–22 Oct) but check-in happened on only two.
    $event = Event::create(['company_id' => $company->id, 'title' => 'Orientation', 'event_date' => now()->subDays(6), 'end_date' => now()->subDays(3), 'day' => 2]);
    unlockAllEventFeatures($event);
    $people = collect(['Both Days', 'Only Day One', 'Never Came'])->map(function ($name) use ($company, $event) {
        $person = Participant::create(['company_id' => $company->id, 'name' => $name, 'gender' => 'Male', 'category' => 'Pastor', 'phone' => '020'.random_int(1000000, 9999999)]);
        $event->registrations()->create(['participant_id' => $person->id, 'status' => 'confirmed']);

        return $person;
    });
    Attendance::create(['event_id' => $event->id, 'participant_id' => $people[0]->id, 'day' => 1]);
    Attendance::create(['event_id' => $event->id, 'participant_id' => $people[0]->id, 'day' => 2, 'method' => 'self']);
    Attendance::create(['event_id' => $event->id, 'participant_id' => $people[1]->id, 'day' => 1]);

    return [$company, $manager, $event, $people];
}

it('measures attendance over the days that had check-ins, not every event day', function () {
    [, , $event] = fourDayEventWithTwoCheckInDays();

    $grid = app(AttendanceGrid::class)->build($event, 'all');

    expect($grid['heldDays']->all())->toBe([1, 2])
        ->and($grid['slots']->pluck('label')->all())->toBe(['Day 1', 'Day 2'])
        ->and($grid['rows']->firstWhere('person.name', 'Both Days')->daysAttended)->toBe(2)
        ->and($grid['totalDays'])->toBe(2)
        // Day 1: 2 of 3 (67%), Day 2: 1 of 3 (33%) → average 50%.
        ->and($grid['dayStats']->pluck('rate')->all())->toEqual([67, 33])
        ->and($grid['turnout'])->toBe(50)
        ->and($grid['absentLabel'])->toBe('Never came');

    // A day with no check-ins is not "everyone absent".
    $dayThree = app(AttendanceGrid::class)->build($event, 3);
    expect($dayThree['held'])->toBeFalse()->and($dayThree['absent'])->toBeEmpty();
    expect(app(AttendanceGrid::class)->build($event, 2)['absentLabel'])->toBe('Absent');
});

it('offers every event day in the day picker, whatever the active day is set to', function () {
    [, $manager, $event] = fourDayEventWithTwoCheckInDays();

    $this->actingAs($manager)
        ->get(route('reports.event', ['event' => $event, 'day' => 'all']))
        ->assertOk()
        ->assertSee('Day 4')
        ->assertSee('No check-ins recorded on Day 3')
        ->assertSee('2 of 3', false)
        ->assertSee('50%');
});

it('counts confirmed attendees as expected on every day, including people who never arrived', function () {
    $company = Company::create(['name' => 'Arrival Co']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Summit', 'event_date' => now()->subDays(3), 'end_date' => now()->subDays(2), 'has_arrival_session' => true, 'arrival_date' => now()->subDays(4)]);
    unlockAllEventFeatures($event);
    $arrived = Participant::create(['company_id' => $company->id, 'name' => 'Arrived']);
    $missing = Participant::create(['company_id' => $company->id, 'name' => 'Never Arrived']);
    foreach ([$arrived, $missing] as $person) {
        $event->registrations()->create(['participant_id' => $person->id, 'status' => 'confirmed']);
    }
    Attendance::create(['event_id' => $event->id, 'participant_id' => $arrived->id, 'day' => 0]);
    Attendance::create(['event_id' => $event->id, 'participant_id' => $arrived->id, 'day' => 1]);

    $dayOne = app(AttendanceGrid::class)->build($event, 1);
    $allDays = app(AttendanceGrid::class)->build($event, 'all');

    expect($dayOne['totalExpected'])->toBe(2)->and($dayOne['absent']->pluck('name')->all())->toBe(['Never Arrived'])
        ->and($allDays['totalExpected'])->toBe(2)
        ->and($allDays['slots']->pluck('label')->all())->toBe(['Arrival', 'Day 1']);
});

it('explains what is and is not counted on the simple view', function () {
    [$company, $manager, $event] = fourDayEventWithTwoCheckInDays();
    $pending = Participant::create(['company_id' => $company->id, 'name' => 'Pending Person']);
    $event->registrations()->create(['participant_id' => $pending->id, 'status' => 'pending']);

    $this->actingAs($manager)
        ->get(route('reports.event', ['event' => $event, 'day' => 'all']))
        ->assertOk()
        ->assertSee('Not counted: 1 pending registration.')
        ->assertDontSee('checked in on arrival')
        ->assertSee('Average daily turnout');
});

it('drops a check-in from live counters and reports once the registration is cancelled', function () {
    [, , $event, $people] = fourDayEventWithTwoCheckInDays();

    expect(Attendance::query()->countedFor($event)->where('day', 1)->count())->toBe(2);

    $event->registrations()->where('participant_id', $people[1]->id)->update(['status' => 'cancelled']);

    expect(Attendance::query()->countedFor($event)->where('day', 1)->count())->toBe(1)
        ->and(app(AttendanceGrid::class)->build($event, 1)['rows']->pluck('person.name')->all())->toBe(['Both Days']);
});

it('applies the on-screen filters to downloads', function () {
    [$company, $manager, $event] = fourDayEventWithTwoCheckInDays();
    $woman = Participant::create(['company_id' => $company->id, 'name' => 'Sister Ama', 'gender' => 'Female']);
    $event->registrations()->create(['participant_id' => $woman->id, 'status' => 'confirmed']);
    Attendance::create(['event_id' => $event->id, 'participant_id' => $woman->id, 'day' => 1]);

    $csv = $this->actingAs($manager)->get(route('reports.csv', ['event' => $event, 'day' => 'all', 'gender' => 'Female']))->assertOk();
    $content = $csv->streamedContent() ?: file_get_contents($csv->baseResponse->getFile()->getPathname());

    expect($content)->toContain('Sister Ama')->not->toContain('Both Days');
});

it('shows drop-offs, people who never came, a comparison and data checks on the advanced view', function () {
    [$company, $manager, $event, $people] = fourDayEventWithTwoCheckInDays();
    $people[2]->update(['gender' => 'Ghana']);
    $other = Event::create(['company_id' => $company->id, 'title' => 'Last Year', 'event_date' => now()->subYear()]);

    $this->actingAs($manager)
        ->get(route('reports.event', ['event' => $event, 'day' => 'all', 'view' => 'advanced', 'compare' => $other->id]))
        ->assertOk()
        ->assertSee('Came, then missed a day (1)', false)
        ->assertSee('Missed Day 2')
        ->assertSee('Never came (1)', false)
        ->assertSee('Last Year')
        ->assertSee('Unusual gender value')
        ->assertSee('participants='.$people[1]->id, false);
});

it('opens the message composer with the people picked on a report', function () {
    [, $manager, $event, $people] = fourDayEventWithTwoCheckInDays();

    $this->actingAs($manager)
        ->get(route('events.messages.create', ['event' => $event, 'participants' => $people[1]->id.','.$people[2]->id]))
        ->assertOk()
        ->assertViewHas('selectedParticipantIds', [$people[1]->id, $people[2]->id]);
});

it('serves the detailed report from cache on a second visit', function () {
    // The real cache stores serialize; the test default (array) does not, which hid a crash.
    config(['cache.default' => 'file']);
    [, $manager, $event] = fourDayEventWithTwoCheckInDays();

    foreach ([1, 2] as $visit) {
        $this->actingAs($manager)
            ->get(route('reports.event', ['event' => $event, 'day' => 'all', 'view' => 'detailed']))
            ->assertOk()
            ->assertSee('Both Days');
    }
});
