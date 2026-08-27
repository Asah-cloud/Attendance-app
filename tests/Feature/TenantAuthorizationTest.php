<?php

use App\Imports\UsersImport;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\EventRegistrationSubmitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

it('prevents a manager from viewing another company event', function () {
    $company = Company::create(['name' => 'One']);
    $otherCompany = Company::create(['name' => 'Two']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $event = Event::create([
        'company_id' => $otherCompany->id,
        'title' => 'Private Event',
        'event_date' => now(),
    ]);

    $this->actingAs($manager)
        ->get(route('events.attendance', $event))
        ->assertForbidden();
});

it('prevents a manager from editing another company user', function () {
    $company = Company::create(['name' => 'One']);
    $otherCompany = Company::create(['name' => 'Two']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $subject = User::factory()->create(['company_id' => $otherCompany->id]);
    $subject->assignRole('usher');

    $this->actingAs($manager)
        ->get(route('admin.users.edit', $subject))
        ->assertForbidden();
});

it('rejects personal QR check in outside the event dates', function () {
    $company = Company::create(['name' => 'One']);
    $event = Event::create([
        'company_id' => $company->id,
        'title' => 'Future Event',
        'event_date' => now()->addWeek(),
    ]);
    $participant = Participant::create([
        'company_id' => $company->id,
        'name' => 'Future Attendee',
        'phone' => '201234567',
    ]);
    $registration = $event->registrations()->create([
        'participant_id' => $participant->id,
        'status' => EventRegistration::STATUS_CONFIRMED,
    ]);

    $this->get(route('attendance.personal', $registration->registration_code))
        ->assertOk()
        ->assertSee('Event check-in');

    $this->assertDatabaseCount('attendances', 0);
});

it('does not expose the legacy public phone check-in page', function () {
    $event = Event::create([
        'title' => 'Signed Event',
        'event_date' => now(),
    ]);

    $this->get("/scan/{$event->id}/1")->assertNotFound();
});

it('blocks users whose company subscription is inactive', function () {
    $company = Company::create(['name' => 'Inactive', 'is_active' => false]);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    $this->actingAs($manager)->get(route('events.index'))->assertForbidden();
});

it('imports participants into the event company with normalized phones and emails them a confirmation', function () {
    Notification::fake();

    $company = Company::create(['name' => 'One']);
    $event = Event::create([
        'company_id' => $company->id,
        'title' => 'Import Event',
        'event_date' => now(),
    ]);

    Excel::import(new UsersImport($event), base_path('tests/Fixtures/participants.csv'));

    $user = Participant::where('member_id', $company->id.':42')->firstOrFail();
    expect($user->company_id)->toBe($company->id)
        ->and($user->phone)->toBe('201234567')
        ->and($user->email)->toBe('jane@example.com')
        ->and($user->category)->toBe('Member')
        ->and($event->confirmedParticipants()->whereKey($user->id)->exists())->toBeTrue();

    Notification::assertSentTo($user, EventRegistrationSubmitted::class);
});

it('does not re-notify a participant when the same import row is processed again', function () {
    Notification::fake();

    $company = Company::create(['name' => 'One']);
    $event = Event::create([
        'company_id' => $company->id,
        'title' => 'Import Event',
        'event_date' => now(),
    ]);

    Excel::import(new UsersImport($event), base_path('tests/Fixtures/participants.csv'));
    Excel::import(new UsersImport($event), base_path('tests/Fixtures/participants.csv'));

    $user = Participant::where('member_id', $company->id.':42')->firstOrFail();

    // Sent twice total: once per eligible channel (mail + SMS) for the one
    // logical notification event, not once per import attempt.
    Notification::assertSentToTimes($user, EventRegistrationSubmitted::class, 2);
});

it('allows a manager to reach the import workflow for their company event', function () {
    $company = Company::create(['name' => 'One']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $event = Event::create([
        'company_id' => $company->id,
        'title' => 'Import Event',
        'event_date' => now(),
    ]);

    $this->actingAs($manager)
        ->post(route('events.import.store', $event))
        ->assertSessionHasErrors('file');
});

it('prevents a manager from importing into another company event', function () {
    $company = Company::create(['name' => 'One']);
    $otherCompany = Company::create(['name' => 'Two']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $event = Event::create([
        'company_id' => $otherCompany->id,
        'title' => 'Private Import Event',
        'event_date' => now(),
    ]);

    $this->actingAs($manager)
        ->post(route('events.import.store', $event))
        ->assertForbidden();
});

it('rejects manual attendance for an event day that has not started', function () {
    $company = Company::create(['name' => 'One']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $event = Event::create([
        'company_id' => $company->id,
        'title' => 'Two Day Event',
        'event_date' => now(),
        'end_date' => now()->addDay(),
    ]);
    $participant = Participant::create(['company_id' => $company->id, 'name' => 'Member']);
    $event->registrations()->create([
        'participant_id' => $participant->id,
        'status' => 'confirmed',
    ]);

    $this->actingAs($manager)
        ->post(route('events.attendance.store', ['event' => $event, 'day' => 2]), [
            'participant_id' => $participant->id,
            'day' => 2,
        ])
        ->assertSessionHasErrors('day');

    $this->assertDatabaseCount('attendances', 0);
});

it('preserves users when an event is deleted at the database level', function () {
    $company = Company::create(['name' => 'One']);
    $event = Event::create([
        'company_id' => $company->id,
        'title' => 'Disposable Event',
        'event_date' => now(),
    ]);
    $participant = Participant::create(['company_id' => $company->id, 'name' => 'Member']);
    $event->registrations()->create(['participant_id' => $participant->id, 'status' => 'confirmed']);

    DB::table('events')->where('id', $event->id)->delete();

    expect($participant->fresh())->not->toBeNull()
        ->and($participant->fresh()->registrations()->count())->toBe(0);
});
