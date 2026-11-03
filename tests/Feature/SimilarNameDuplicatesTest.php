<?php

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\User;
use App\Services\SimilarNames;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('manager');
    $this->company = Company::create(['name' => 'Lookalike Org']);
    $this->manager = User::factory()->create(['company_id' => $this->company->id, 'role' => 'manager']);
    $this->manager->assignRole('manager');
    $this->event = Event::create(['company_id' => $this->company->id, 'title' => 'Lookalike Conference', 'event_date' => now(), 'has_arrival_session' => true, 'arrival_date' => now()->toDateString()]);
    unlockAllEventFeatures($this->event);
    $this->register = function (string $name, array $extra = []): EventRegistration {
        $person = Participant::create(['company_id' => $this->company->id, 'name' => $name, ...$extra]);

        return EventRegistration::create(['event_id' => $this->event->id, 'participant_id' => $person->id, 'status' => EventRegistration::STATUS_CONFIRMED]);
    };
});

it('matches spelling, order and middle-name variants but not different people', function (string $a, string $b, bool $same) {
    expect(app(SimilarNames::class)->sameName($a, $b))->toBe($same);
})->with([
    ['Ebenezer Quao', 'Ebenzer Quao', true],
    ['Sayeweh Charles', 'Charles Garvan Sayeweh', true],
    ['Teye Adimey Ebenezer', 'Ebenezer Adimeh Teye', true],
    ['Pastor Kwame Asante', 'Asante Kwame', true],
    ['Kwame Asante', 'Kofi Asante', false],
    ['Kwame Asante', 'Asante', false],
    ['Joseph Tetteh', 'Joseph Mensah', false],
]);

it('treats two different phone numbers or genders as two people', function () {
    $names = app(SimilarNames::class);
    $a = new Participant(['name' => 'Alex Boateng', 'phone' => '0242376987']);
    $b = new Participant(['name' => 'Alex Kwasi Boateng', 'phone' => '0249612296']);
    $c = new Participant(['name' => 'Alex Boateng', 'gender' => 'Male']);
    $d = new Participant(['name' => 'Alex Boateng', 'gender' => 'Female']);
    $a->id = 1;
    $b->id = 2;
    $c->id = 3;
    $d->id = 4;

    expect($names->samePerson($a, $b))->toBeFalse()->and($names->samePerson($c, $d))->toBeFalse();
});

it('asks before manually adding someone with a similar name, then adds on confirmation', function () {
    ($this->register)('Ebenezer Quao', ['gender' => 'Male']);
    $data = ['name' => 'Ebenzer Quao', 'gender' => 'Male', 'category' => 'Member'];

    $this->actingAs($this->manager)->post(route('events.registrations.store', $this->event), $data)
        ->assertRedirect()->assertSessionHas('similar_people', fn ($people) => $people[0]['name'] === 'Ebenezer Quao');
    expect($this->event->registrations()->count())->toBe(1);

    $this->actingAs($this->manager)->post(route('events.registrations.store', $this->event), [...$data, 'confirm_new' => '1'])
        ->assertSessionHas('success');
    expect($this->event->registrations()->count())->toBe(2);
});

it('lists spelling variants in the possible duplicates panel', function () {
    ($this->register)('Charles Garvan Sayeweh');
    ($this->register)('Sayeweh Charles');

    $this->actingAs($this->manager)->get(route('events.registrations.index', $this->event))
        ->assertOk()->assertSee('Possible duplicate attendees')->assertSee('Sayeweh Charles');
});

it('warns at the scanner when someone with a similar name is already checked in', function () {
    $first = ($this->register)('Adwoa Agyei Nkansah', ['gender' => 'Female']);
    $second = ($this->register)('Adwoa Nkansah', ['gender' => 'Female']);
    Attendance::create(['event_id' => $this->event->id, 'participant_id' => $first->participant_id, 'day' => 0, 'session' => 1]);

    $this->actingAs($this->manager)
        ->postJson(route('events.arrival.scanner.check-in', $this->event), ['registration_code' => $second->registration_code])
        ->assertOk()
        ->assertJson(['successful' => true, 'possible_duplicate' => true])
        ->assertJsonFragment(['message' => 'Welcome, Adwoa Nkansah! Your Arrival check-in is complete. We are happy to have you here. Possible duplicate: Adwoa Agyei Nkansah was already checked in at '.Attendance::first()->created_at->format('D g:i A').'. Please check this is a different person.']);
});

it('does not warn at the scanner once the pair was marked as different people', function () {
    $first = ($this->register)('Adwoa Agyei Nkansah');
    $second = ($this->register)('Adwoa Nkansah');
    EventRegistration::whereIn('id', [$first->id, $second->id])->update(['duplicate_dismissed_at' => now()]);
    Attendance::create(['event_id' => $this->event->id, 'participant_id' => $first->participant_id, 'day' => 0, 'session' => 1]);

    $this->actingAs($this->manager)
        ->postJson(route('events.arrival.scanner.check-in', $this->event), ['registration_code' => $second->registration_code])
        ->assertOk()->assertJson(['possible_duplicate' => false]);
});
