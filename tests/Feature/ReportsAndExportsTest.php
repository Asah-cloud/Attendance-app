<?php

use App\Exports\AreaAttendanceSummaryExport;
use App\Exports\AttendanceExport;
use App\Exports\AttendanceWorkbook;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
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

it('allows a manager to view the summary report for their own event', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = reportsManager($company);
    $event = reportsEventWithAttendee($company);

    $this->actingAs($manager)
        ->get(route('reports.summary', $event))
        ->assertOk()
        ->assertSee('Attendee');
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
        ->get(route('reports.event', ['event' => $event, 'day' => 1]))
        ->assertOk()
        ->assertSee('Male Guest')
        ->assertSee('Female Member')
        ->assertSee('Guest · 1', false)
        ->assertSee('Member · 1', false)
        ->assertSee('Male · 1', false)
        ->assertSee('Female · 1', false);

    $this->actingAs($manager)
        ->get(route('reports.event', ['event' => $event, 'day' => 1, 'gender' => 'Male']))
        ->assertOk()
        ->assertSee('Male Guest')
        ->assertDontSee('Female Member')
        ->assertViewHas('totalExpected', 1)
        ->assertViewHas('areaBreakdown', fn ($areas) => $areas->all() === ['Kumasi Area' => 1]);

    $this->actingAs($manager)
        ->get(route('reports.event', ['event' => $event, 'day' => 1, 'category' => 'Member']))
        ->assertOk()
        ->assertSee('Female Member')
        ->assertDontSee('Male Guest');

    $this->actingAs($manager)
        ->get(route('reports.event', ['event' => $event, 'day' => 'all', 'area' => 'Kumasi Area']))
        ->assertOk()
        ->assertSee('Present people by area')
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
        ->get(route('reports.event', ['event' => $event, 'day' => 'all']))
        ->assertOk()
        ->assertSee('Participant 7')
        ->assertSee('Staff · all days')
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
    expect(array_map(fn ($sheet) => $sheet->title(), $sheets))->toBe(['Summary', 'Present', 'Not present'])
        ->and($sheets[2]->array())->toHaveCount(1)
        ->and($sheets[2]->array()[0][1])->toBe('Never Came');
});

it('leaves days that have not happened yet blank instead of marking people absent', function () {
    $company = Company::create(['name' => 'Grid Co']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Running Event', 'event_date' => now(), 'end_date' => now()->addDays(2)]);
    unlockAllEventFeatures($event);
    $person = Participant::create(['company_id' => $company->id, 'name' => 'Early Bird']);
    $event->registrations()->create(['participant_id' => $person->id, 'status' => 'confirmed']);
    Attendance::create(['event_id' => $event->id, 'participant_id' => $person->id, 'day' => 1]);

    $cells = app(\App\Services\AttendanceGrid::class)->build($event, 'all')['rows']->first()->cells;

    expect($cells['2:1'])->toBe('')->and($cells['3:1'])->toBe('')->and($cells['1:1'])->not->toBe('');
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
