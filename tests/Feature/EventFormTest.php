<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\Form;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function formsManager(Company $company): User
{
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    return $manager;
}

it('lets a manager create a form and add questions to it', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = formsManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Annual Conference', 'event_date' => now()]);

    $this->actingAs($manager)
        ->post(route('events.forms.store', $event), ['title' => 'Feedback', 'description' => 'How did we do?'])
        ->assertRedirect();

    $this->assertDatabaseHas('forms', ['event_id' => $event->id, 'title' => 'Feedback']);
    $form = Form::where('event_id', $event->id)->first();
    expect($form->slug)->not->toBeEmpty();

    $this->actingAs($manager)
        ->post(route('events.forms.fields.store', [$event, $form]), [
            'label' => 'How satisfied were you?',
            'field_type' => 'select',
            'is_required' => '1',
            'options' => "Very satisfied\nSatisfied\nNot satisfied",
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('form_fields', ['form_id' => $form->id, 'label' => 'How satisfied were you?', 'field_type' => 'select']);
});

it('lets a manager edit and remove a question', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = formsManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Annual Conference', 'event_date' => now()]);
    $form = $event->forms()->create(['title' => 'Feedback']);
    $field = $form->fields()->create(['field_key' => 'field_1', 'label' => 'Comments', 'field_type' => 'textarea', 'display_order' => 10]);

    $this->actingAs($manager)
        ->patch(route('events.forms.fields.update', [$event, $form, $field]), ['label' => 'Any comments?', 'is_required' => '1'])
        ->assertRedirect();

    $this->assertDatabaseHas('form_fields', ['id' => $field->id, 'label' => 'Any comments?', 'is_required' => true]);

    $this->actingAs($manager)
        ->delete(route('events.forms.fields.destroy', [$event, $form, $field]))
        ->assertRedirect();

    $this->assertDatabaseMissing('form_fields', ['id' => $field->id]);
});

it('lets anyone with the link view an open form and submit a response', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Annual Conference', 'event_date' => now()]);
    $form = $event->forms()->create(['title' => 'Feedback', 'is_open' => true]);
    $form->fields()->create(['field_key' => 'comments', 'label' => 'Comments', 'field_type' => 'textarea', 'is_required' => true, 'display_order' => 10]);

    $this->get(route('forms.show', [$event->slug, $form->slug]))
        ->assertOk()
        ->assertSee('Feedback');

    $this->post(route('forms.store', [$event->slug, $form->slug]), [
        'answers' => ['comments' => 'Great event!'],
    ])->assertRedirect(route('forms.thank-you', [$event->slug, $form->slug]));

    $this->assertDatabaseHas('form_responses', ['form_id' => $form->id]);
    $response = $form->responses()->first();
    expect($response->answers['comments'])->toBe('Great event!');
});

it('does not accept a response for a closed form', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Annual Conference', 'event_date' => now()]);
    $form = $event->forms()->create(['title' => 'Feedback', 'is_open' => false]);

    $this->get(route('forms.show', [$event->slug, $form->slug]))
        ->assertOk()
        ->assertSee('not currently accepting responses');

    $this->post(route('forms.store', [$event->slug, $form->slug]), ['answers' => []])
        ->assertNotFound();

    $this->assertDatabaseCount('form_responses', 0);
});

it('lets a manager view responses and export them as excel and pdf', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = formsManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Annual Conference', 'event_date' => now()]);
    $form = $event->forms()->create(['title' => 'Feedback', 'is_open' => true]);
    $form->fields()->create(['field_key' => 'comments', 'label' => 'Comments', 'field_type' => 'textarea', 'display_order' => 10]);
    $form->responses()->create(['answers' => ['comments' => 'Loved it']]);

    $this->actingAs($manager)
        ->get(route('events.forms.responses', [$event, $form]))
        ->assertOk()
        ->assertSee('Loved it');

    $excel = $this->actingAs($manager)->get(route('events.forms.responses.export', [$event, $form]));
    $excel->assertOk();
    expect($excel->headers->get('Content-Disposition'))->toContain('.xlsx');

    $pdf = $this->actingAs($manager)->get(route('events.forms.responses.pdf', [$event, $form]));
    $pdf->assertOk();
    expect($pdf->headers->get('Content-Type'))->toContain('application/pdf');
});

it('gives two forms with the same title in the same event distinct slugs', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Annual Conference', 'event_date' => now()]);

    $first = $event->forms()->create(['title' => 'Feedback']);
    $second = $event->forms()->create(['title' => 'Feedback']);

    expect($first->slug)->not->toBe($second->slug);
});

it('renders the forms index, create, edit, and QR pages for a manager', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = formsManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Annual Conference', 'event_date' => now()]);
    $form = $event->forms()->create(['title' => 'Feedback', 'is_open' => true]);

    $this->actingAs($manager)->get(route('events.forms.index', $event))->assertOk()->assertSee('Feedback');
    $this->actingAs($manager)->get(route('events.forms.create', $event))->assertOk();
    $this->actingAs($manager)->get(route('events.forms.edit', [$event, $form]))->assertOk()->assertSee($form->slug);
    $this->actingAs($manager)->get(route('events.forms.print-qr', [$event, $form]))->assertOk();
});

it('renders the public thank-you page after submitting', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Annual Conference', 'event_date' => now()]);
    $form = $event->forms()->create(['title' => 'Feedback', 'is_open' => true]);

    $this->get(route('forms.thank-you', [$event->slug, $form->slug]))
        ->assertOk()
        ->assertSee('Thanks for your response');
});

it('prevents an usher from managing forms', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $usher = User::factory()->create(['company_id' => $company->id, 'role' => 'usher']);
    $usher->assignRole('usher');
    $event = Event::create(['company_id' => $company->id, 'title' => 'Annual Conference', 'event_date' => now()]);

    $this->actingAs($usher)->get(route('events.forms.index', $event))->assertForbidden();
});

it('prevents a manager from managing another company event forms', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $otherCompany = Company::create(['name' => 'Other Co']);
    $manager = formsManager($company);
    $event = Event::create(['company_id' => $otherCompany->id, 'title' => 'Private Event', 'event_date' => now()]);

    $this->actingAs($manager)->get(route('events.forms.index', $event))->assertForbidden();
});
