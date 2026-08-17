<?php

use App\Exports\AttendanceExport;
use App\Livewire\AddWalkInModal;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\RegistrationLifecycleNotification;
use App\Services\ParticipantRegistrationService;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

it('reuses a participant across events and creates separate registrations', function () {
    $company = Company::create(['name' => 'One']);
    $firstEvent = Event::create(['company_id' => $company->id, 'title' => 'First', 'event_date' => now()]);
    $secondEvent = Event::create(['company_id' => $company->id, 'title' => 'Second', 'event_date' => now()]);
    $service = app(ParticipantRegistrationService::class);

    [$firstUser] = $service->register($firstEvent, [
        'name' => 'Shared Participant',
        'email' => 'shared@example.com',
        'phone' => '+233 20 123 4567',
    ], 'walk_in');
    [$secondUser] = $service->register($secondEvent, [
        'name' => 'Shared Participant',
        'email' => 'shared@example.com',
        'phone' => '0201234567',
    ], 'import');

    expect($secondUser->id)->toBe($firstUser->id)
        ->and(Participant::where('email', 'shared@example.com')->count())->toBe(1)
        ->and($firstUser->events()->count())->toBe(2)
        ->and($firstUser->registrations()->where('status', 'confirmed')->count())->toBe(2);
});

it('does not allow public personal QR links to mark attendance', function () {
    $company = Company::create(['name' => 'One']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Today', 'event_date' => now()]);
    $confirmed = Participant::create(['company_id' => $company->id, 'name' => 'Confirmed', 'phone' => '201111111']);
    $unregistered = Participant::create(['company_id' => $company->id, 'name' => 'Other', 'phone' => '202222222']);
    $confirmedRegistration = $event->registrations()->create(['participant_id' => $confirmed->id, 'status' => 'confirmed']);
    $unconfirmedRegistration = $event->registrations()->create(['participant_id' => $unregistered->id, 'status' => 'pending']);

    $this->get(route('attendance.personal', $confirmedRegistration->registration_code))
        ->assertOk()
        ->assertSee('Staff scan required');
    $this->get(route('attendance.personal', $unconfirmedRegistration->registration_code))
        ->assertOk()
        ->assertSee('Staff scan required');

    $this->assertDatabaseMissing('attendances', ['event_id' => $event->id, 'participant_id' => $confirmed->id]);
    $this->assertDatabaseMissing('attendances', ['event_id' => $event->id, 'participant_id' => $unregistered->id]);
});

it('uses confirmed registrations for policy access totals reports and exports', function () {
    $company = Company::create(['name' => 'One']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Today', 'event_date' => now()]);
    $confirmed = Participant::create(['company_id' => $company->id, 'name' => 'Confirmed Person']);
    $pending = Participant::create(['company_id' => $company->id, 'name' => 'Pending Person']);
    $usher = User::factory()->create(['company_id' => $company->id]);
    $usher->assignRole('usher');
    $usher->events()->attach($event);
    $event->registrations()->create(['participant_id' => $confirmed->id, 'status' => EventRegistration::STATUS_CONFIRMED]);
    $event->registrations()->create(['participant_id' => $pending->id, 'status' => EventRegistration::STATUS_PENDING]);
    Attendance::create(['event_id' => $event->id, 'participant_id' => $confirmed->id, 'day' => 1, 'status' => 'present']);

    expect($usher->can('view', $event))->toBeTrue()
        ->and($event->confirmedParticipants()->count())->toBe(1)
        ->and((new AttendanceExport($event, 1))->collection()->pluck('id')->all())->toBe([$confirmed->id]);

    $this->actingAs($usher)
        ->get(route('reports.event', ['event' => $event, 'day' => 1]))
        ->assertOk()
        ->assertSee('Confirmed Person')
        ->assertDontSee('Pending Person');
});

it('removes a participant from one event without deleting their account or other registration', function () {
    $company = Company::create(['name' => 'One']);
    $firstEvent = Event::create(['company_id' => $company->id, 'title' => 'First', 'event_date' => now()]);
    $secondEvent = Event::create(['company_id' => $company->id, 'title' => 'Second', 'event_date' => now()]);
    $participant = Participant::create(['company_id' => $company->id, 'name' => 'Member']);
    $firstEvent->registrations()->create(['participant_id' => $participant->id, 'status' => 'confirmed']);
    $secondEvent->registrations()->create(['participant_id' => $participant->id, 'status' => 'confirmed']);

    $firstEvent->registrations()->where('participant_id', $participant->id)->delete();

    expect($participant->fresh())->not->toBeNull()
        ->and($firstEvent->confirmedParticipants()->whereKey($participant->id)->exists())->toBeFalse()
        ->and($secondEvent->confirmedParticipants()->whereKey($participant->id)->exists())->toBeTrue();
});

it('reuses an existing participant when a manager adds a walk in', function () {
    Notification::fake();
    $company = Company::create(['name' => 'One']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Today', 'event_date' => now()]);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $participant = Participant::create([
        'company_id' => $company->id,
        'name' => 'Existing Walk In',
        'email' => 'walkin@example.com',
        'phone' => '201234567',
    ]);

    $this->actingAs($manager);
    Livewire::test(AddWalkInModal::class, ['event' => $event])
        ->set('name', 'Updated Walk In')
        ->set('email', 'walkin@example.com')
        ->set('phone', '0201234567')
        ->call('registerWalkIn')
        ->assertHasNoErrors();

    expect(Participant::where('email', 'walkin@example.com')->count())->toBe(1)
        ->and($participant->fresh()->name)->toBe('Updated Walk In')
        ->and($event->confirmedParticipants()->whereKey($participant->id)->exists())->toBeTrue();

    Notification::assertSentTo(
        $participant,
        RegistrationLifecycleNotification::class,
        fn ($notification) => $notification->type === 'confirmed'
            && $notification->registration->registration_code !== null
    );
});

it('allows a manager to assign a participant to multiple company events', function () {
    $company = Company::create(['name' => 'One']);
    $firstEvent = Event::create(['company_id' => $company->id, 'title' => 'First', 'event_date' => now()]);
    $secondEvent = Event::create(['company_id' => $company->id, 'title' => 'Second', 'event_date' => now()]);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $participant = User::factory()->create(['company_id' => $company->id]);
    $participant->assignRole('usher');

    $this->actingAs($manager)
        ->put(route('admin.users.update', $participant), [
            'name' => $participant->name,
            'email' => $participant->email,
            'role' => 'usher',
            'company_id' => $company->id,
            'event_ids' => [$firstEvent->id, $secondEvent->id],
        ])
        ->assertRedirect(route('admin.users.index'));

    expect($participant->events()->pluck('events.id')->all())
        ->toEqualCanonicalizing([$firstEvent->id, $secondEvent->id]);
});
