<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use Illuminate\Database\QueryException;

it('stores registration settings with the expected defaults and casts', function () {
    $event = Event::create([
        'title' => 'Registration Event',
        'event_date' => now()->addWeek(),
    ]);

    expect($event->registration_enabled)->toBeFalse()
        ->and($event->registration_requires_approval)->toBeFalse()
        ->and($event->registration_capacity)->toBeNull();

    $event->update([
        'registration_enabled' => true,
        'registration_opens_at' => now(),
        'registration_closes_at' => now()->addDays(5),
        'registration_capacity' => 250,
        'registration_requires_approval' => true,
    ]);

    $event->refresh();

    expect($event->registration_enabled)->toBeTrue()
        ->and($event->registration_requires_approval)->toBeTrue()
        ->and($event->registration_capacity)->toBe(250)
        ->and($event->registration_opens_at)->not->toBeNull()
        ->and($event->registration_closes_at)->not->toBeNull();
});

it('registers one participant for multiple events', function () {
    $company = Company::create(['name' => 'One']);
    $legacyEvent = Event::create([
        'company_id' => $company->id,
        'title' => 'Legacy Event',
        'event_date' => now(),
    ]);
    $secondEvent = Event::create([
        'company_id' => $company->id,
        'title' => 'Second Event',
        'event_date' => now()->addWeek(),
    ]);
    $participant = Participant::create(['company_id' => $company->id, 'name' => 'Member']);

    $legacyRegistration = $legacyEvent->registrations()->create([
        'participant_id' => $participant->id,
        'status' => EventRegistration::STATUS_CONFIRMED,
        'approved_at' => now(),
    ]);
    $secondEvent->registrations()->create([
        'participant_id' => $participant->id,
        'status' => EventRegistration::STATUS_PENDING,
    ]);

    expect($legacyRegistration->registration_code)->toHaveLength(40)
        ->and($legacyRegistration->registered_at)->not->toBeNull()
        ->and($participant->events()->pluck('events.id')->all())
        ->toEqualCanonicalizing([$legacyEvent->id, $secondEvent->id])
        ->and($legacyEvent->registeredParticipants()->firstOrFail()->id)->toBe($participant->id)
        ->and($legacyEvent->registeredParticipants()->firstOrFail()->pivot->status)
        ->toBe(EventRegistration::STATUS_CONFIRMED);
});

it('prevents duplicate registrations for the same participant and event', function () {
    $event = Event::create([
        'title' => 'Unique Registration Event',
        'event_date' => now(),
    ]);
    $participant = Participant::create(['name' => 'Member']);

    $event->registrations()->create(['participant_id' => $participant->id]);

    expect(fn () => $event->registrations()->create(['participant_id' => $participant->id]))
        ->toThrow(QueryException::class);
});

it('removes registrations when their event is deleted but preserves the participant', function () {
    $event = Event::create([
        'title' => 'Disposable Registration Event',
        'event_date' => now(),
    ]);
    $participant = Participant::create(['name' => 'Member']);
    $event->registrations()->create(['participant_id' => $participant->id]);

    $event->delete();

    expect($participant->fresh())->not->toBeNull();
    $this->assertDatabaseCount('event_registrations', 0);
});
