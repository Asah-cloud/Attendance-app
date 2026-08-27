<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\EventRegistrationSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function publicRegistrationEvent(array $overrides = []): Event
{
    $company = Company::create(['name' => fake()->unique()->company()]);
    $event = Event::create(array_merge([
        'company_id' => $company->id,
        'title' => 'Public Registration Event',
        'event_date' => now()->addWeek(),
        'registration_enabled' => true,
        'registration_opens_at' => now()->subHour(),
        'registration_closes_at' => now()->addDay(),
        'registration_terms' => 'I agree that my details may be used to administer this event.',
        'registration_terms_version' => '2026-08',
    ], $overrides));
    $event->ensureSystemRegistrationFields();

    return $event;
}

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Public Attendee',
        'email' => 'public@example.com',
        'phone' => '+233 20 123 4567',
        'gender' => 'Female',
        'category' => 'Guest',
        'consent' => '1',
    ], $overrides);
}

it('allows managers to configure their event form while protecting system fields', function () {
    $event = publicRegistrationEvent();
    expect(route('events.register', $event))
        ->toContain('/events/public-registration-event/register')
        ->not->toContain('/events/'.$event->id.'/register');
    $manager = User::factory()->create(['company_id' => $event->company_id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $this->actingAs($manager)
        ->get(route('events.registration-form.edit', $event))
        ->assertOk()
        ->assertSee('Protected system fields')
        ->assertSee('Copy link')
        ->assertSee(route('events.register', $event));

    $this->actingAs($manager)
        ->get(route('events.registration-form.print-qr', $event))
        ->assertOk()
        ->assertSee('Scan to register for this event');

    $this->actingAs($manager)
        ->get(route('events.registration-form.download-qr', $event))
        ->assertOk()
        ->assertHeader('content-type', 'image/svg+xml');

    expect($event->registrationFields()->where('is_system', true)->count())->toBe(6);
    $systemField = $event->registrationFields()->where('field_key', 'email')->firstOrFail();

    $this->actingAs($manager)
        ->delete(route('events.registration-fields.destroy', [$event, $systemField]))
        ->assertStatus(422);

    $this->actingAs($manager)
        ->post(route('events.registration-fields.store', $event), [
            'label' => 'T-shirt size',
            'field_type' => 'select',
            'is_required' => '1',
            'options' => "Small\nMedium\nLarge",
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('event_registration_fields', [
        'event_id' => $event->id,
        'label' => 'T-shirt size',
        'is_system' => false,
        'is_required' => true,
    ]);
});

it('prevents managers from editing another company registration form', function () {
    $event = publicRegistrationEvent();
    $otherCompany = Company::create(['name' => 'Other']);
    $manager = User::factory()->create(['company_id' => $otherCompany->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    $this->actingAs($manager)->get(route('events.registration-form.edit', $event))->assertForbidden();
});

it('rejects disabled and closed registration forms', function () {
    $disabled = publicRegistrationEvent(['registration_enabled' => false]);
    $closed = publicRegistrationEvent([
        'registration_opens_at' => now()->subDays(2),
        'registration_closes_at' => now()->subDay(),
    ]);

    $this->get(route('events.register', $disabled))->assertNotFound();
    $this->post(route('events.register.store', $closed), registrationPayload())
        ->assertSessionHasErrors('registration');
    $this->assertDatabaseCount('event_registrations', 0);
});

it('registers publicly with normalized details custom answers consent and notification', function () {
    Notification::fake();
    $event = publicRegistrationEvent();
    $field = $event->registrationFields()->create([
        'field_key' => 'custom_tshirt',
        'label' => 'T-shirt size',
        'field_type' => 'select',
        'is_required' => true,
        'options' => ['Small', 'Medium', 'Large'],
        'display_order' => 50,
    ]);

    $response = $this->post(route('events.register.store', $event), registrationPayload([
        'custom' => [$field->field_key => 'Medium'],
    ]));

    $registration = EventRegistration::with('participant')->firstOrFail();
    $response->assertRedirect(route('registrations.confirmation', $registration->registration_code));

    expect($registration->status)->toBe('confirmed')
        ->and($registration->registration_code)->toHaveLength(40)
        ->and($registration->participant->phone)->toBe('201234567')
        ->and($registration->custom_answers)->toBe(['custom_tshirt' => 'Medium'])
        ->and($registration->consented_at)->not->toBeNull()
        ->and($registration->terms_version)->toBe('2026-08');
    Notification::assertSentTo($registration->participant, EventRegistrationSubmitted::class);
});

it('prevents duplicate registration and reuses the existing participant', function () {
    Notification::fake();
    $event = publicRegistrationEvent();

    $this->post(route('events.register.store', $event), registrationPayload());
    $this->post(route('events.register.store', $event), registrationPayload(['phone' => '0201234567']))
        ->assertSessionHasErrors('email');

    expect(Participant::where('email', 'public@example.com')->count())->toBe(1)
        ->and(EventRegistration::count())->toBe(1);
});

it('waitlists registrations after capacity is reached', function () {
    Notification::fake();
    $event = publicRegistrationEvent(['registration_capacity' => 1]);

    $this->post(route('events.register.store', $event), registrationPayload());
    $this->post(route('events.register.store', $event), registrationPayload([
        'name' => 'Second Attendee',
        'email' => 'second@example.com',
        'phone' => '0209999999',
    ]));

    expect(EventRegistration::orderBy('id')->pluck('status')->all())->toBe(['confirmed', 'waitlisted']);
});

it('marks registrations pending when manager approval is required', function () {
    Notification::fake();
    $event = publicRegistrationEvent(['registration_requires_approval' => true]);

    $this->post(route('events.register.store', $event), registrationPayload());

    expect(EventRegistration::firstOrFail()->status)->toBe('pending');
});

it('allows cancellation through the private registration code', function () {
    Notification::fake();
    $event = publicRegistrationEvent();
    $this->post(route('events.register.store', $event), registrationPayload());
    $registration = EventRegistration::firstOrFail();

    $this->get(route('registrations.confirmation', $registration->registration_code))
        ->assertOk()
        ->assertSee('Public Registration Event');
    $this->post(route('registrations.cancel', $registration->registration_code))
        ->assertSessionHas('success');

    expect($registration->fresh()->status)->toBe('cancelled')
        ->and($registration->fresh()->cancelled_at)->not->toBeNull();
});

it('lets organizers manage registrations, export them, and resend confirmations', function () {
    Notification::fake();
    $event = publicRegistrationEvent(['registration_requires_approval' => true]);
    $manager = User::factory()->create(['company_id' => $event->company_id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $this->post(route('events.register.store', $event), registrationPayload());
    $registration = EventRegistration::with('participant')->firstOrFail();

    $this->actingAs($manager)->patch(route('events.registrations.approve', [$event, $registration]))->assertSessionHas('success');
    expect($registration->fresh()->status)->toBe('confirmed');

    $this->actingAs($manager)->post(route('events.registrations.resend', [$event, $registration]))->assertSessionHas('success');
    Notification::assertSentTo($registration->participant, EventRegistrationSubmitted::class);

    $this->actingAs($manager)->patch(route('events.registrations.reject', [$event, $registration]))->assertSessionHas('success');
    expect($registration->fresh()->status)->toBe('rejected');

    $this->actingAs($manager)->patch(route('events.registrations.cancel', [$event, $registration]))->assertSessionHas('success');
    expect($registration->fresh()->status)->toBe('cancelled')
        ->and($registration->fresh()->cancelled_at)->not->toBeNull();

    $this->actingAs($manager)->post(route('events.registrations.store', $event), [
        'name' => 'Manual Attendee', 'email' => 'manual@example.com', 'phone' => '', 'gender' => 'Male', 'category' => 'Member',
    ])->assertSessionHas('success');
    $this->assertDatabaseHas('participants', ['email' => 'manual@example.com']);
    $this->assertDatabaseHas('event_registrations', ['event_id' => $event->id, 'source' => 'manual', 'status' => 'confirmed']);

    $this->actingAs($manager)->get(route('events.registrations.export', $event))
        ->assertOk()->assertDownload();
});

it('renders the manager attendee list with gender and category columns', function () {
    $event = publicRegistrationEvent();
    $manager = User::factory()->create(['company_id' => $event->company_id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $participant = Participant::create(['company_id' => $event->company_id, 'name' => 'Attendee One', 'email' => 'one@example.com', 'category' => 'Guest', 'gender' => 'Female']);
    $event->registrations()->create(['participant_id' => $participant->id, 'status' => 'confirmed']);

    $this->actingAs($manager)->get(route('events.registrations.index', $event))
        ->assertOk()
        ->assertSee('Attendee One')
        ->assertSee('Female')
        ->assertSee('Gender');
});

it('lets a manager turn the category field into a dropdown and enforces its options', function () {
    $event = publicRegistrationEvent();
    $manager = User::factory()->create(['company_id' => $event->company_id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $categoryField = $event->registrationFields()->where('field_key', 'category')->firstOrFail();

    $this->actingAs($manager)->patch(route('events.registration-fields.update', [$event, $categoryField]), [
        'label' => 'Attendee category',
        'field_type' => 'select',
        'options' => "Member\nVisitor",
    ])->assertSessionHas('success');

    expect($categoryField->fresh())
        ->field_type->toBe('select')
        ->options->toBe(['Member', 'Visitor']);

    $this->actingAs($manager)->get(route('events.registrations.index', $event))
        ->assertOk()
        ->assertSee('Select attendee type');

    Notification::fake();
    $this->post(route('events.register.store', $event), registrationPayload(['category' => 'Not A Real Option']))
        ->assertSessionHasErrors('category');
    $this->post(route('events.register.store', $event), registrationPayload(['category' => 'Visitor']))
        ->assertRedirect();
});

it('lets a manager edit an attendee\'s details', function () {
    $event = publicRegistrationEvent();
    $manager = User::factory()->create(['company_id' => $event->company_id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $participant = Participant::create(['company_id' => $event->company_id, 'name' => 'Old Name', 'email' => 'old@example.com', 'category' => 'Guest']);
    $registration = $event->registrations()->create(['participant_id' => $participant->id, 'status' => 'confirmed']);

    $this->actingAs($manager)->patch(route('events.registrations.participant.update', [$event, $registration]), [
        'name' => 'New Name',
        'email' => 'new@example.com',
        'phone' => '0201234567',
        'gender' => 'Male',
        'category' => 'VIP',
        'member_id' => 'M-100',
    ])->assertSessionHas('success');

    expect($participant->fresh())
        ->name->toBe('New Name')
        ->email->toBe('new@example.com')
        ->category->toBe('VIP')
        ->member_id->toBe('M-100');
});

it('logs an audit entry for each changed field when a manager edits an attendee', function () {
    $event = publicRegistrationEvent();
    $manager = User::factory()->create(['company_id' => $event->company_id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $participant = Participant::create(['company_id' => $event->company_id, 'name' => 'Old Name', 'email' => 'old@example.com', 'category' => 'Guest', 'gender' => 'Male']);
    $registration = $event->registrations()->create(['participant_id' => $participant->id, 'status' => 'confirmed']);

    $this->actingAs($manager)->patch(route('events.registrations.participant.update', [$event, $registration]), [
        'name' => 'New Name',
        'email' => 'old@example.com',
        'gender' => 'Male',
        'category' => 'VIP',
    ]);

    $log = $participant->auditLogs()->firstOrFail();
    expect($log->user_id)->toBe($manager->id)
        ->and($log->changes)->toHaveKey('name', ['old' => 'Old Name', 'new' => 'New Name'])
        ->and($log->changes)->toHaveKey('category', ['old' => 'Guest', 'new' => 'VIP'])
        ->and($log->changes)->not->toHaveKey('email')
        ->and($log->changes)->not->toHaveKey('gender');

    $this->actingAs($manager)
        ->get(route('events.registrations.participant.history', [$event, $registration]))
        ->assertOk()
        ->assertSee('Old Name')
        ->assertSee('New Name')
        ->assertSee($manager->name);
});

it('does not log an audit entry when an edit submits no actual changes', function () {
    $event = publicRegistrationEvent();
    $manager = User::factory()->create(['company_id' => $event->company_id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $participant = Participant::create(['company_id' => $event->company_id, 'name' => 'Same Name', 'email' => 'same@example.com', 'category' => 'Guest', 'gender' => 'Male']);
    $registration = $event->registrations()->create(['participant_id' => $participant->id, 'status' => 'confirmed']);

    $this->actingAs($manager)->patch(route('events.registrations.participant.update', [$event, $registration]), [
        'name' => 'Same Name',
        'email' => 'same@example.com',
        'gender' => 'Male',
        'category' => 'Guest',
    ]);

    expect($participant->auditLogs()->count())->toBe(0);
});

it('generates printable badges with a scannable QR for each confirmed attendee', function () {
    $event = publicRegistrationEvent();
    $event->company()->update(['name' => 'Acme Organization', 'logo_path' => 'company-logos/acme.png']);
    $event->update(['logo_path' => 'event-logos/conference.png', 'location' => 'Accra Conference Centre']);
    $manager = User::factory()->create(['company_id' => $event->company_id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $confirmed = Participant::create(['company_id' => $event->company_id, 'name' => 'Badge Person', 'category' => 'VIP', 'member_id' => 'MEM-42']);
    $confirmedReg = $event->registrations()->create(['participant_id' => $confirmed->id, 'status' => 'confirmed']);
    $pending = Participant::create(['company_id' => $event->company_id, 'name' => 'Pending Person']);
    $event->registrations()->create(['participant_id' => $pending->id, 'status' => 'pending']);

    $this->actingAs($manager)
        ->get(route('events.badges', $event))
        ->assertOk()
        ->assertSee('Badge Person')
        ->assertSee('VIP')
        ->assertSee('MEM-42')
        ->assertSee('Acme Organization')
        ->assertSee('Accra Conference Centre')
        ->assertSee(Storage::url('company-logos/acme.png'))
        ->assertSee(Storage::url('event-logos/conference.png'))
        ->assertSee('Attendance &amp; meal collection', false)
        ->assertDontSee('Pending Person')
        ->assertSee('<svg', false);
});

it('uses an event pass fallback and compacts long badge names', function () {
    $event = publicRegistrationEvent();
    $manager = User::factory()->create(['company_id' => $event->company_id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $participant = Participant::create([
        'company_id' => $event->company_id,
        'name' => 'Asah Ayensu Kofi Isaac',
        'category' => 'Member',
        'member_id' => null,
    ]);
    $event->registrations()->create(['participant_id' => $participant->id, 'status' => 'confirmed']);

    $this->actingAs($manager)->get(route('events.badges', $event))
        ->assertOk()
        ->assertSee('Asah A. K. Isaac')
        ->assertSee('Event Pass')
        ->assertDontSee('Guest attendee')
        ->assertSee('margin:3mm', false);
});

it('prevents an usher from printing badges', function () {
    $event = publicRegistrationEvent();
    $usher = User::factory()->create(['company_id' => $event->company_id, 'role' => 'usher']);
    $usher->assignRole('usher');

    $this->actingAs($usher)->get(route('events.badges', $event))->assertForbidden();
});

it('lets a manager choose A5 or A6 badges and save category colours', function () {
    $event = publicRegistrationEvent();
    $manager = User::factory()->create(['company_id' => $event->company_id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $vip = Participant::create(['company_id' => $event->company_id, 'name' => 'VIP Person', 'category' => 'VIP']);
    $event->registrations()->create(['participant_id' => $vip->id, 'status' => 'confirmed']);

    $this->actingAs($manager)->patch(route('events.badges.settings', $event), [
        'badge_size' => 'A5',
        'badge_design' => 'category',
        'categories' => ['VIP'],
        'colors' => ['#FF5500'],
    ])->assertRedirect()->assertSessionHas('success');

    expect($event->fresh())
        ->badge_size->toBe('A5')
        ->badge_design->toBe('category')
        ->badge_category_colors->toBe(['VIP' => '#FF5500']);

    $this->actingAs($manager)->get(route('events.badges', $event))
        ->assertOk()->assertSee('A5')->assertSee('#FF5500');
});

it('lets a manager customize a sectioned badge with uploaded artwork', function () {
    Storage::fake('public');
    $event = publicRegistrationEvent();
    $manager = User::factory()->create(['company_id' => $event->company_id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $participant = Participant::create(['company_id' => $event->company_id, 'name' => 'Custom Badge Person', 'category' => 'Delegate']);
    $event->registrations()->create(['participant_id' => $participant->id, 'status' => 'confirmed']);

    $this->actingAs($manager)->patch(route('events.badges.settings', $event), [
        'badge_size' => 'A6',
        'badge_design' => 'default',
        'badge_layout' => 'split',
        'badge_image' => UploadedFile::fake()->image('badge-art.jpg', 1200, 800),
        'badge_primary_color' => '#115E59',
        'badge_accent_color' => '#172554',
        'badge_image_position_x' => 35,
        'badge_image_position_y' => 70,
    ])->assertRedirect()->assertSessionHas('success');

    $event->refresh();
    expect($event)
        ->badge_layout->toBe('split')
        ->badge_primary_color->toBe('#115E59')
        ->badge_accent_color->toBe('#172554')
        ->badge_image_position_x->toBe(35)
        ->badge_image_position_y->toBe(70)
        ->badge_image_path->not->toBeNull();
    Storage::disk('public')->assertExists($event->badge_image_path);

    $this->actingAs($manager)->get(route('events.badges', $event))
        ->assertOk()
        ->assertSee('Customize Badge')
        ->assertSee('layout-split')
        ->assertSee(Storage::url($event->badge_image_path));

    $this->actingAs($manager)->get(route('events.badges.pdf', $event))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('requires artwork for a customized badge layout', function () {
    $event = publicRegistrationEvent();
    $manager = User::factory()->create(['company_id' => $event->company_id, 'role' => 'manager']);
    $manager->assignRole('manager');

    $this->actingAs($manager)->from(route('events.badges', $event))->patch(route('events.badges.settings', $event), [
        'badge_size' => 'A6',
        'badge_design' => 'default',
        'badge_layout' => 'image_header',
        'badge_primary_color' => '#0F766E',
        'badge_accent_color' => '#0F172A',
        'badge_image_position_x' => 50,
        'badge_image_position_y' => 50,
    ])->assertRedirect(route('events.badges', $event))->assertSessionHasErrors('badge_image');

    expect($event->fresh()->badge_layout)->toBe('standard');
});

it('prevents an usher from editing attendee details', function () {
    $event = publicRegistrationEvent();
    $usher = User::factory()->create(['company_id' => $event->company_id, 'role' => 'usher']);
    $usher->assignRole('usher');
    $participant = Participant::create(['company_id' => $event->company_id, 'name' => 'Attendee', 'email' => 'attendee@example.com']);
    $registration = $event->registrations()->create(['participant_id' => $participant->id, 'status' => 'confirmed']);

    $this->actingAs($usher)->patch(route('events.registrations.participant.update', [$event, $registration]), [
        'name' => 'Hacked Name', 'category' => 'Guest',
    ])->assertForbidden();

    expect($participant->fresh()->name)->toBe('Attendee');
});

it('prevents managers from controlling another company registrations', function () {
    $event = publicRegistrationEvent();
    $participant = Participant::create(['company_id' => $event->company_id, 'name' => 'Attendee', 'email' => 'attendee@example.com']);
    $registration = $event->registrations()->create(['participant_id' => $participant->id, 'status' => 'pending']);
    $otherCompany = Company::create(['name' => 'Other organizer']);
    $manager = User::factory()->create(['company_id' => $otherCompany->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    $this->actingAs($manager)->patch(route('events.registrations.approve', [$event, $registration]))->assertForbidden();
    $this->actingAs($manager)->get(route('events.registrations.export', $event))->assertForbidden();
    $this->actingAs($manager)->patch(route('events.registrations.participant.update', [$event, $registration]), [
        'name' => 'Hacked Name', 'category' => 'Guest',
    ])->assertForbidden();
});
