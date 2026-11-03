<?php

use App\Livewire\ArrivalStats;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\User;
use App\Services\AttendanceGrid;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('manager');
    $this->company = Company::create(['name' => 'Placeholder Org']);
    $this->manager = User::factory()->create(['company_id' => $this->company->id, 'role' => 'manager']);
    $this->manager->assignRole('manager');
    $this->event = Event::create(['company_id' => $this->company->id, 'title' => 'Placeholder Conference', 'event_date' => now(), 'has_arrival_session' => true]);
    $this->register = function (string $name): Participant {
        $person = Participant::create(['company_id' => $this->company->id, 'name' => $name]);
        EventRegistration::create(['event_id' => $this->event->id, 'participant_id' => $person->id, 'status' => EventRegistration::STATUS_CONFIRMED]);

        return $person;
    };
});

it('recognises numbered placeholder names', function (string $name, bool $placeholder) {
    expect(Participant::isNumberedPlaceholderName($name))->toBe($placeholder);
})->with([
    ['Participant 12', true],
    ['PARTICIPANT 201', true],
    [' participant12 ', true],
    ['Participant #7', true],
    ['Participant', false],
    ['Participant Kwame Asante', false],
    ['Kwame Asante', false],
]);

it('leaves unused numbered badges out of expected until they are scanned', function () {
    ($this->register)('Kwame Asante');
    ($this->register)('Ama Mensah');
    $used = ($this->register)('Participant 1');
    ($this->register)('Participant 2');
    ($this->register)('PARTICIPANT 3');

    expect($this->event->expectedParticipants()->count())->toBe(2)
        ->and($this->event->unusedPlaceholderIds())->toHaveCount(3);

    Attendance::create(['event_id' => $this->event->id, 'participant_id' => $used->id, 'day' => 0, 'session' => 1]);

    expect($this->event->expectedParticipants()->count())->toBe(3)
        ->and($this->event->unusedPlaceholderIds())->toHaveCount(2);

    $grid = app(AttendanceGrid::class)->build($this->event->fresh(), 0);
    expect($grid['totalExpected'])->toBe(3)
        ->and($grid['unusedPlaceholders'])->toBe(2)
        ->and($grid['absent']->pluck('name')->all())->toEqualCanonicalizing(['Kwame Asante', 'Ama Mensah']);
});

it('counts a renamed numbered badge like anyone else', function () {
    $person = ($this->register)('Participant 9');
    expect($this->event->expectedParticipants()->count())->toBe(0);

    $person->update(['name' => 'Kofi Boateng']);

    expect($this->event->expectedParticipants()->count())->toBe(1);
});

it('keeps unused numbered badges scannable at arrival', function () {
    $spare = ($this->register)('Participant 4');

    expect($this->event->confirmedParticipants()->whereKey($spare->id)->exists())->toBeTrue();
});

it('shows expected and the unused badge note on the arrival counters', function () {
    ($this->register)('Kwame Asante');
    ($this->register)('Participant 1');
    ($this->register)('Participant 2');

    $this->actingAs($this->manager);

    Livewire::test(ArrivalStats::class, ['event' => $this->event])
        ->assertViewHas('confirmed', 1)
        ->assertSee('Expected')
        ->assertSee('2 unused numbered badges');
});

it('shows the unused badge note on the simple report', function () {
    ($this->register)('Kwame Asante');
    ($this->register)('Participant 1');

    $this->actingAs($this->manager)
        ->get(route('reports.event', ['event' => $this->event, 'day' => 'all']))
        ->assertOk()
        ->assertSee('1 unused numbered badge');
});
