<?php

use App\Livewire\AttendanceSearch;
use App\Models\Attendance;
use App\Models\BadgePrint;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MealDistribution;
use App\Models\Participant;
use App\Models\User;
use App\Services\EventRegistrationResolver;
use App\Services\ParticipantMergeService;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('manager');
    $this->company = Company::create(['name' => 'Merge Org']);
    $this->manager = User::factory()->create(['company_id' => $this->company->id, 'role' => 'manager']);
    $this->manager->assignRole('manager');
    $this->event = Event::create(['company_id' => $this->company->id, 'title' => 'Merge Conference', 'event_date' => now()]);
    unlockAllEventFeatures($this->event);
    $this->register = function (string $name): EventRegistration {
        $person = Participant::create(['company_id' => $this->company->id, 'name' => $name]);

        return EventRegistration::create(['event_id' => $this->event->id, 'participant_id' => $person->id, 'status' => EventRegistration::STATUS_CONFIRMED]);
    };
});

it('keeps the merged-away badge scanning and carries its food and print history over', function () {
    $kept = ($this->register)('Ebenezer Quao');
    $gone = ($this->register)('Ebenzer Quao');
    $oldCode = $gone->registration_code;
    $meal = MealDistribution::create(['event_id' => $this->event->id, 'name' => 'Lunch', 'total_portions' => 10]);
    $meal->collections()->create(['event_registration_id' => $gone->id, 'participant_id' => $gone->participant_id, 'issued_by' => $this->manager->id, 'quantity' => 1, 'collected_at' => now()]);
    BadgePrint::recordDetails($this->event->id, [$gone->id => ['Custom text' => 'PARTICIPANT']], $this->manager->id);
    Attendance::create(['event_id' => $this->event->id, 'participant_id' => $gone->participant_id, 'day' => 0, 'session' => 1]);

    app(ParticipantMergeService::class)->merge($kept->participant, $gone->participant, $this->manager);

    expect(EventRegistration::find($gone->id))->toBeNull()
        ->and(app(EventRegistrationResolver::class)->fromScan($this->event, 'ASAH-ATTENDANCE:'.$oldCode)?->id)->toBe($kept->id)
        ->and(app(EventRegistrationResolver::class)->fromScan($this->event, 'ASAH-ATTENDANCE:'.$kept->registration_code)?->id)->toBe($kept->id)
        ->and($meal->collections()->where('event_registration_id', $kept->id)->value('quantity'))->toBe(1)
        ->and(BadgePrint::where('event_registration_id', $kept->id)->count())->toBe(1)
        ->and(Attendance::where('event_id', $this->event->id)->where('participant_id', $kept->participant_id)->count())->toBe(1);

    $this->get(route('attendance.personal', $oldCode))->assertOk()->assertSee('Ebenezer Quao');
});

it('adds portions together when both records collected the same meal', function () {
    $kept = ($this->register)('Adwoa Nkansah');
    $gone = ($this->register)('Adjoa Nkansah');
    $meal = MealDistribution::create(['event_id' => $this->event->id, 'name' => 'Lunch', 'total_portions' => 10]);
    foreach ([$kept, $gone] as $registration) {
        $meal->collections()->create(['event_registration_id' => $registration->id, 'participant_id' => $registration->participant_id, 'issued_by' => $this->manager->id, 'quantity' => 1, 'collected_at' => now()]);
    }

    app(ParticipantMergeService::class)->merge($kept->participant, $gone->participant, $this->manager);

    expect($meal->collections()->count())->toBe(1)
        ->and($meal->collections()->value('quantity'))->toBe(2);
});

it('removes from the attendee list by cancelling, and restores with the same badge', function () {
    $registration = ($this->register)('Participant 81');
    $code = $registration->registration_code;

    $this->actingAs($this->manager);
    Livewire::test(AttendanceSearch::class, ['event' => $this->event])->call('deleteUser', $registration->participant_id);

    $registration->refresh();
    expect($registration->status)->toBe(EventRegistration::STATUS_CANCELLED)
        ->and($registration->registration_code)->toBe($code);

    $this->patch(route('events.registrations.approve', [$this->event, $registration]))->assertRedirect();

    expect($registration->fresh()->status)->toBe(EventRegistration::STATUS_CONFIRMED)
        ->and(app(EventRegistrationResolver::class)->fromScan($this->event, 'ASAH-ATTENDANCE:'.$code)?->id)->toBe($registration->id);
});

it('labels the button for a cancelled registration as Restore', function () {
    $registration = ($this->register)('Kwame Asante');
    $registration->update(['status' => EventRegistration::STATUS_CANCELLED]);

    $this->actingAs($this->manager)->get(route('events.registrations.index', $this->event))->assertOk()->assertSee('Restore');
});
