<?php

use App\Imports\UsersImport;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\EventRegistrationSubmitted;
use Illuminate\Http\UploadedFile;
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

it('never assigns an usher to another company\'s event, even when a super admin edits them', function () {
    $company = Company::create(['name' => 'One']);
    $otherCompany = Company::create(['name' => 'Two']);
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');
    $usher = User::factory()->create(['company_id' => $company->id, 'role' => 'usher']);
    $usher->assignRole('usher');
    $ownEvent = Event::create(['company_id' => $company->id, 'title' => 'Own Event', 'event_date' => now()]);
    $otherEvent = Event::create(['company_id' => $otherCompany->id, 'title' => 'Other Company Event', 'event_date' => now()]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $usher), [
            'name' => $usher->name,
            'email' => $usher->email,
            'role' => 'usher',
            'company_id' => $company->id,
            'event_ids' => [$ownEvent->id, $otherEvent->id],
        ])
        ->assertRedirect(route('admin.users.index'));

    expect($usher->events()->pluck('events.id')->all())->toBe([$ownEvent->id]);
});

it('does not let an usher access an event staffed by mistake outside their own company', function () {
    $company = Company::create(['name' => 'One']);
    $otherCompany = Company::create(['name' => 'Two']);
    $usher = User::factory()->create(['company_id' => $company->id, 'role' => 'usher']);
    $usher->assignRole('usher');
    $otherEvent = Event::create(['company_id' => $otherCompany->id, 'title' => 'Other Company Event', 'event_date' => now()]);
    $usher->events()->attach($otherEvent);

    $this->actingAs($usher)
        ->get(route('events.attendance', $otherEvent))
        ->assertForbidden();

    expect($usher->can('scanAttendance', $otherEvent))->toBeFalse();
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

it('imports participants into the event company with normalized phones without sending notifications', function () {
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
        ->and($user->gender)->toBe('Female')
        ->and($user->room_group)->toBe('Kumasi Area')
        ->and($event->confirmedParticipants()->whereKey($user->id)->exists())->toBeTrue();

    Notification::assertNothingSent();
});

it('does not notify a participant when the same import row is processed again', function () {
    Notification::fake();

    $company = Company::create(['name' => 'One']);
    $event = Event::create([
        'company_id' => $company->id,
        'title' => 'Import Event',
        'event_date' => now(),
    ]);

    Excel::import(new UsersImport($event), base_path('tests/Fixtures/participants.csv'));
    Excel::import(new UsersImport($event), base_path('tests/Fixtures/participants.csv'));

    Participant::where('member_id', $company->id.':42')->firstOrFail();
    Notification::assertNothingSent();
});

it('prefers a contact match when a trusted import row id collides with another participant', function () {
    $company = Company::create(['name' => 'One']);
    $event = Event::create([
        'company_id' => $company->id,
        'title' => 'Import Event',
        'event_date' => now(),
    ]);
    $wrongIdOwner = Participant::create([
        'company_id' => $company->id,
        'name' => 'Existing Member 41',
        'member_id' => $company->id.':41',
        'phone' => '201111111',
    ]);
    $contactOwner = Participant::create([
        'company_id' => $company->id,
        'name' => 'Existing Esther',
        'member_id' => $company->id.':282',
        'phone' => '504343053',
    ]);

    $import = new UsersImport($event);
    $import->importRow(['41', 'Esther Nyarko Agyemang', 'Female', 'Koforidua', 'Participant', '0504343053'], 2);

    expect($event->registrations()->where('participant_id', $contactOwner->id)->exists())->toBeTrue()
        ->and($contactOwner->fresh()->name)->toBe('Esther Nyarko Agyemang')
        ->and($contactOwner->fresh()->member_id)->toBe($company->id.':282')
        ->and($wrongIdOwner->fresh()->name)->toBe('Existing Member 41')
        ->and($wrongIdOwner->registrations()->exists())->toBeFalse();
});

it('imports every row as a distinct participant when the id column is blank, and skips only the header', function () {
    $company = Company::create(['name' => 'One']);
    $event = Event::create([
        'company_id' => $company->id,
        'title' => 'Import Event',
        'event_date' => now(),
    ]);

    Excel::import(new UsersImport($event), base_path('tests/Fixtures/participants_blank_department.csv'));

    expect(Participant::where('company_id', $company->id)->pluck('name')->sort()->values()->all())
        ->toBe(['Siloam 1', 'Siloam 2', 'Siloam 3']);
});

it('sends notifications after a registered participant import when requested', function () {
    Notification::fake();

    $company = Company::create(['name' => 'One']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $event = Event::create([
        'company_id' => $company->id,
        'title' => 'Import Event',
        'event_date' => now(),
    ]);

    $file = UploadedFile::fake()->createWithContent(
        'participants.csv',
        file_get_contents(base_path('tests/Fixtures/participants.csv'))
    );

    $this->actingAs($manager)
        ->post(route('events.import.store', $event), [
            'file' => $file,
            'send_notifications' => '1',
        ])
        ->assertSessionHas('success', 'Participants imported successfully! Email and SMS notifications are being sent.');

    $participant = Participant::where('member_id', $company->id.':42')->firstOrFail();
    Notification::assertSentTo($participant, EventRegistrationSubmitted::class);
});

it('imports participants from a text-based PDF table', function () {
    $company = Company::create(['name' => 'One']);
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $event = Event::create([
        'company_id' => $company->id,
        'title' => 'PDF Import Event',
        'event_date' => now(),
    ]);

    $dompdf = new Dompdf\Dompdf;
    $dompdf->loadHtml(<<<'HTML'
        <table>
            <thead><tr><th>ID</th><th>Name</th><th>Gender</th><th>Area</th><th>Category</th><th>Phone</th><th>Email</th></tr></thead>
            <tbody><tr><td>77</td><td>PDF Guest</td><td>Female</td><td>Kumasi North</td><td>Participant</td><td>0241234567</td><td>pdf@example.com</td></tr></tbody>
        </table>
        HTML);
    $dompdf->render();
    $file = UploadedFile::fake()->createWithContent('participants.pdf', $dompdf->output());

    $this->actingAs($manager)
        ->post(route('events.import.store', $event), ['file' => $file])
        ->assertSessionHas('success');

    $participant = Participant::where('member_id', $company->id.':77')->firstOrFail();
    expect($participant->name)->toBe('PDF Guest')
        ->and($participant->gender)->toBe('Female')
        ->and($participant->room_group)->toBe('Kumasi North')
        ->and($participant->phone)->toBe('241234567')
        ->and($participant->email)->toBe('pdf@example.com');
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
