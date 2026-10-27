<?php

use App\Exports\RegistrationReportWorkbook;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use App\Services\RegistrationReport;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
    $this->company = Company::create(['name' => 'Acme Co']);
    $this->manager = User::factory()->create(['company_id' => $this->company->id, 'role' => 'manager']);
    $this->manager->assignRole('manager');
    // Two days, starting next week: nobody can have checked in yet.
    $this->event = Event::create(['company_id' => $this->company->id, 'title' => 'World Conference', 'event_date' => now()->addWeek(), 'end_date' => now()->addWeek()->addDay()]);
    unlockAllEventFeatures($this->event);
    foreach ([
        ['Kofi', 'Male', 'Pastor', 'Accra', '0241234567', 'confirmed'],
        ['Ama', 'Female', 'Member', 'Accra', '0201234567', 'confirmed'],
        ['Chidi', 'Male', 'Member', 'Lagos', '+2348031234567', 'confirmed'],
        ['Grace', 'Female', 'Pastor', 'London', '+447700900009', 'pending'],
        ['Yaw', 'Male', 'Member', 'Kumasi', null, 'cancelled'],
    ] as [$name, $gender, $category, $area, $phone, $status]) {
        $person = Participant::create(['company_id' => $this->company->id, 'name' => $name, 'gender' => $gender, 'phone' => $phone]);
        $this->event->registrations()->create(['participant_id' => $person->id, 'status' => $status, 'category' => $category, 'room_group' => $area]);
    }
    $staff = Participant::create(['company_id' => $this->company->id, 'name' => 'Usher Staff', 'is_support_staff' => true]);
    $this->event->registrations()->create(['participant_id' => $staff->id, 'status' => 'confirmed']);
});

it('shows who is coming before the event starts, split by category, gender, country and area', function () {
    $report = app(RegistrationReport::class)->build($this->event);

    expect($report['rows']->pluck('person.name')->all())->toBe(['Ama', 'Chidi', 'Kofi'])
        ->and($report['filters'])->toBe(['status' => 'confirmed'])
        ->and($report['statusCounts']->all())->toBe(['confirmed' => 3, 'pending' => 1, 'cancelled' => 1])
        ->and($report['breakdowns']['Category']->all())->toBe(['Member' => 2, 'Pastor' => 1])
        ->and($report['breakdowns']['Gender']->all())->toBe(['Male' => 2, 'Female' => 1])
        ->and($report['breakdowns']['Country']->all())->toBe(['Ghana' => 2, 'Nigeria' => 1])
        ->and($report['breakdowns']['Area']->all())->toBe(['Accra' => 2, 'Lagos' => 1])
        ->and($report['checkedIn'])->toBe(0);

    $this->actingAs($this->manager)->get(route('reports.registrations', $this->event))->assertOk()
        ->assertSee('Registration report')
        ->assertSee("The event hasn't started: this is who is coming.")
        ->assertSee('Confirmed (3)')->assertSee('Pending approval (1)')->assertSee('All statuses (5)')
        ->assertSee('By country')->assertSee('Nigeria')
        ->assertSeeInOrder(['Ama', 'Chidi', 'Kofi'])
        ->assertSee('Not yet')
        ->assertDontSee('Grace')->assertDontSee('Usher Staff');
});

it('filters by status, category, gender, country and area', function () {
    $names = fn (array $filters) => app(RegistrationReport::class)->build($this->event, $filters)['rows']->pluck('person.name')->all();

    expect($names(['status' => 'all']))->toBe(['Ama', 'Chidi', 'Grace', 'Kofi', 'Yaw'])
        ->and($names(['status' => 'pending']))->toBe(['Grace'])
        ->and($names(['category' => 'Pastor']))->toBe(['Kofi'])
        ->and($names(['status' => 'all', 'category' => 'Pastor']))->toBe(['Grace', 'Kofi'])
        ->and($names(['gender' => 'Female']))->toBe(['Ama'])
        ->and($names(['status' => 'all', 'country' => 'United Kingdom']))->toBe(['Grace'])
        ->and($names(['status' => 'all', 'country' => 'Country not given']))->toBe(['Yaw'])
        ->and($names(['area' => 'Accra']))->toBe(['Ama', 'Kofi'])
        // An unknown status falls back to confirmed.
        ->and($names(['status' => 'nonsense']))->toBe(['Ama', 'Chidi', 'Kofi']);

    $this->actingAs($this->manager)->get(route('reports.registrations', ['event' => $this->event, 'status' => 'all', 'country' => 'Nigeria']))->assertOk()
        ->assertSee('Showing: All statuses · Country: Nigeria')
        ->assertSee(route('reports.registrations.excel', ['event' => $this->event->id, 'status' => 'all', 'country' => 'Nigeria']))
        ->assertSee('Chidi')->assertDontSee('Kofi');
});

it('downloads the registrations as Excel and PDF, following the filters', function () {
    $response = $this->actingAs($this->manager)->get(route('reports.registrations.excel', ['event' => $this->event, 'status' => 'all']));
    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('World_Conference_Registrations_All.xlsx');

    $path = tempnam(sys_get_temp_dir(), 'registrations').'.xlsx';
    file_put_contents($path, Excel::raw(new RegistrationReportWorkbook($this->event, ['status' => 'all']), ExcelFormat::XLSX));
    $book = IOFactory::load($path);
    $people = collect($book->getSheetByName('People')->toArray(null, false, false));
    $summary = collect($book->getSheetByName('Summary')->toArray(null, false, false));

    expect($people->first(fn ($row) => in_array('#', $row, true)))->toBe(['#', 'Name', 'Phone', 'Email', 'Country', 'Area', 'Category', 'Gender', 'Status', 'Registered', 'Checked in'])
        ->and($people->first(fn ($row) => in_array('Grace', $row, true)))->toContain('+447700900009', 'United Kingdom', 'London', 'Pastor', 'Pending approval', 'Not yet')
        ->and($people->filter(fn ($row) => is_int($row[0] ?? null))->count())->toBe(5)
        ->and($summary->first(fn ($row) => ($row[0] ?? null) === 'Cancelled'))->toBe(['Cancelled', 1, null])
        ->and($summary->first(fn ($row) => ($row[0] ?? null) === 'United Kingdom'))->toBe(['United Kingdom', 1, '20%']);

    $pdf = $this->actingAs($this->manager)->get(route('reports.registrations.pdf', ['event' => $this->event, 'country' => 'Ghana']));
    $pdf->assertOk();
    expect($pdf->headers->get('Content-Disposition'))->toContain('World_Conference_Registrations_confirmed_Ghana.pdf');
});

it('shows how many days each person checked in once the event is under way', function () {
    $this->event->update(['event_date' => now()->subDay(), 'end_date' => now()]);
    $kofi = Participant::where('name', 'Kofi')->first();
    Attendance::create(['event_id' => $this->event->id, 'participant_id' => $kofi->id, 'day' => 1, 'status' => 'present']);

    $report = app(RegistrationReport::class)->build($this->event->fresh());
    expect($report['checkedIn'])->toBe(1)
        ->and(RegistrationReport::checkedInLabel($report['rows']->firstWhere('person.name', 'Kofi'), 2))->toBe('1 of 2 days');

    $this->actingAs($this->manager)->get(route('reports.registrations', $this->event))->assertOk()
        ->assertSee('1 of 2 days')->assertSee('the Checked in column shows who has arrived');
});

it('links the Registrations tab from the attendance report', function () {
    $this->actingAs($this->manager)->get(route('reports.event', ['event' => $this->event, 'day' => 'all']))->assertOk()
        ->assertSee(route('reports.registrations', $this->event), false)
        ->assertSee('Who is coming, at any time');
});

it('keeps another company out of the registration report', function () {
    $outsider = User::factory()->create(['company_id' => Company::create(['name' => 'Other Co'])->id, 'role' => 'manager']);
    $outsider->assignRole('manager');

    foreach (['reports.registrations', 'reports.registrations.excel', 'reports.registrations.pdf'] as $route) {
        $this->actingAs($outsider)->get(route($route, $this->event))->assertForbidden();
    }
});
