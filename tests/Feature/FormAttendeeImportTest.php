<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Participant;
use App\Models\User;
use App\Services\FormAttendeeImporter;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
    Notification::fake();
    $this->company = Company::create(['name' => 'Acme Co']);
    $this->manager = User::factory()->create(['company_id' => $this->company->id, 'role' => 'manager']);
    $this->manager->assignRole('manager');
    $this->event = Event::create(['company_id' => $this->company->id, 'title' => 'Quiz Competition', 'slug' => 'quiz', 'event_date' => now()->addDay()]);
    $this->form = $this->event->forms()->create(['title' => 'Contestants', 'is_open' => true]);
    foreach ([
        ['full_name', 'Full Name', 'text'],
        ['church', 'Church Name', 'text'],
        ['phone', 'Phone Number', 'text'],
        ['email', 'Email Address', 'text'],
        ['gender', 'Gender', 'select', ['Male', 'Female']],
        ['district', 'District', 'text'],
        ['agree', 'I agree to the rules', 'checkbox'],
    ] as $order => $field) {
        $this->form->fields()->create(['field_key' => $field[0], 'label' => $field[1], 'field_type' => $field[2], 'options' => $field[3] ?? null, 'display_order' => $order]);
    }
});

function formResponse(Form $form, array $answers): FormResponse
{
    return FormResponse::create(['form_id' => $form->id, 'answers' => $answers]);
}

it('guesses which question holds each attendee detail from the question titles', function () {
    $mapping = app(FormAttendeeImporter::class)->mapping($this->form);

    expect($mapping)->toMatchArray([
        'name' => 'full_name', // not "Church Name"
        'last_name' => null,
        'phone' => 'phone',
        'email' => 'email',
        'gender' => 'gender',
        'room_group' => 'district',
        'category' => null,
    ]);
    // A tick box cannot hold a person's details
    expect(app(FormAttendeeImporter::class)->mappableFields($this->form)->pluck('field_key'))->not->toContain('agree');
});

it('combines a separate first name and surname', function () {
    $form = $this->event->forms()->create(['title' => 'Split names', 'is_open' => true]);
    $form->fields()->create(['field_key' => 'first', 'label' => 'First Name', 'field_type' => 'text', 'display_order' => 1]);
    $form->fields()->create(['field_key' => 'last', 'label' => 'Surname', 'field_type' => 'text', 'display_order' => 2]);
    formResponse($form, ['first' => 'Ama', 'last' => 'Owusu']);

    expect(app(FormAttendeeImporter::class)->mapping($form))->toMatchArray(['name' => 'first', 'last_name' => 'last']);
    app(FormAttendeeImporter::class)->addAll($form);

    expect(Participant::sole()->name)->toBe('Ama Owusu');
});

it('adds existing responses to the attendee list once, linking people already on it', function () {
    $known = Participant::create(['company_id' => $this->company->id, 'name' => 'Efua Mensah', 'phone' => '244000111', 'category' => 'Member']);
    $knownRegistration = EventRegistration::create(['event_id' => $this->event->id, 'participant_id' => $known->id, 'status' => EventRegistration::STATUS_CONFIRMED, 'category' => 'Member']);

    $kofi = formResponse($this->form, ['full_name' => 'Kofi Asare', 'phone' => '0201234567', 'email' => 'kofi@example.com', 'gender' => 'Male', 'district' => 'Accra Central']);
    $efua = formResponse($this->form, ['full_name' => 'Efua Mensah', 'phone' => '0244000111']);
    $blank = formResponse($this->form, ['full_name' => '  ', 'phone' => '0555000000']);

    $this->actingAs($this->manager)
        ->post(route('events.forms.attendees', [$this->event, $this->form]), [
            'mapping' => ['name' => 'full_name', 'phone' => 'phone', 'email' => 'email', 'gender' => 'gender', 'room_group' => 'district'],
            'action' => 'add_now',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', '1 added to the attendee list, 1 already on the list, 1 skipped (no name given).');

    $registration = $kofi->fresh()->registration;
    expect($registration->status)->toBe(EventRegistration::STATUS_CONFIRMED)
        ->and($registration->source)->toBe('form')
        ->and($registration->room_group)->toBe('Accra Central')
        ->and($registration->participant->only(['name', 'email', 'phone', 'gender']))->toBe(['name' => 'Kofi Asare', 'email' => 'kofi@example.com', 'phone' => '201234567', 'gender' => 'Male'])
        ->and($efua->fresh()->event_registration_id)->toBe($knownRegistration->id)
        ->and($blank->fresh()->event_registration_id)->toBeNull();
    expect($this->form->fresh()->attendee_mapping['name'])->toBe('full_name');

    // Running it again adds nobody twice
    $this->actingAs($this->manager)
        ->post(route('events.forms.attendees', [$this->event, $this->form]), ['mapping' => ['name' => 'full_name', 'phone' => 'phone'], 'action' => 'add_now']);
    expect(EventRegistration::where('event_id', $this->event->id)->count())->toBe(2);
    Notification::assertNothingSent();
});

it('adds new responses automatically when switched on, without sending messages', function () {
    $this->actingAs($this->manager)
        ->post(route('events.forms.attendees', [$this->event, $this->form]), [
            'mapping' => ['name' => 'full_name', 'phone' => 'phone'],
            'auto_add_attendees' => '1',
            'action' => 'save',
        ])
        ->assertSessionHas('success', 'Saved. New responses will be added to the attendee list automatically.');
    auth()->logout();

    $this->post(route('forms.store', ['quiz', $this->form->slug]), ['answers' => ['full_name' => 'Yaw Boateng', 'phone' => '0277000222']])
        ->assertRedirect(route('forms.thank-you', ['quiz', $this->form->slug]));

    $registration = FormResponse::sole()->registration;
    expect($registration)->not->toBeNull()
        ->and($registration->participant->name)->toBe('Yaw Boateng')
        ->and($registration->event_id)->toBe($this->event->id);
    Notification::assertNothingSent();
});

it('leaves responses off the attendee list until the manager sets it up', function () {
    $this->post(route('forms.store', ['quiz', $this->form->slug]), ['answers' => ['full_name' => 'Yaw Boateng']]);

    expect(FormResponse::sole()->event_registration_id)->toBeNull()
        ->and(EventRegistration::count())->toBe(0);
});

it('does not let an existing attendee\'s details be overwritten from a public form', function () {
    $known = Participant::create(['company_id' => $this->company->id, 'name' => 'Efua Mensah', 'phone' => '244000111', 'email' => 'efua@example.com']);
    $this->form->update(['attendee_mapping' => ['name' => 'full_name', 'phone' => 'phone', 'email' => 'email'], 'auto_add_attendees' => true]);

    $this->post(route('forms.store', ['quiz', $this->form->slug]), ['answers' => ['full_name' => 'Someone Else', 'phone' => '0244000111', 'email' => 'other@example.com']]);

    expect($known->fresh()->only(['name', 'email']))->toBe(['name' => 'Efua Mensah', 'email' => 'efua@example.com']);
});

it('shows the set-up panel and who is on the list to managers on the responses page', function () {
    $this->form->update(['attendee_mapping' => ['name' => 'full_name']]);
    formResponse($this->form, ['full_name' => 'Kofi Asare']);
    app(FormAttendeeImporter::class)->addAll($this->form->fresh());
    formResponse($this->form, ['full_name' => 'Ama Owusu']);

    $this->actingAs($this->manager)->get(route('events.forms.responses', [$this->event, $this->form]))
        ->assertOk()
        ->assertSee('Add respondents to the attendee list')
        ->assertSee('Save and add 1 response(s) now')
        ->assertSee('On list');
});

it('needs a name question and only lets managers set it up', function () {
    $this->actingAs($this->manager)
        ->post(route('events.forms.attendees', [$this->event, $this->form]), ['mapping' => ['name' => ''], 'action' => 'save'])
        ->assertSessionHasErrors(['mapping.name' => 'Choose the question that asks for the person\'s name.']);

    $this->actingAs($this->manager)
        ->post(route('events.forms.attendees', [$this->event, $this->form]), ['mapping' => ['name' => 'agree']])
        ->assertSessionHasErrors('mapping.name');

    $usher = User::factory()->create(['company_id' => $this->company->id, 'role' => 'usher']);
    $usher->assignRole('usher');
    $this->actingAs($usher)
        ->post(route('events.forms.attendees', [$this->event, $this->form]), ['mapping' => ['name' => 'full_name']])
        ->assertForbidden();
});
