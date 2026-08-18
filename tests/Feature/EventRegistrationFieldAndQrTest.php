<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function fieldManager(Company $company): User
{
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    return $manager;
}

it('allows a manager to add a custom registration field to their event', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = fieldManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Fielded Event', 'event_date' => now()]);

    $this->actingAs($manager)
        ->post(route('events.registration-fields.store', $event), [
            'label' => 'T-Shirt Size',
            'field_type' => 'text',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('event_registration_fields', [
        'event_id' => $event->id,
        'label' => 'T-Shirt Size',
        'is_system' => false,
    ]);
});

it('requires at least two options for a select registration field', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = fieldManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Fielded Event', 'event_date' => now()]);

    $this->actingAs($manager)
        ->post(route('events.registration-fields.store', $event), [
            'label' => 'Meal',
            'field_type' => 'select',
            'options' => 'Vegetarian',
        ])
        ->assertSessionHasErrors('options');

    $this->assertDatabaseMissing('event_registration_fields', ['event_id' => $event->id, 'label' => 'Meal']);
});

it('allows a manager to delete a custom registration field', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = fieldManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Fielded Event', 'event_date' => now()]);
    $field = $event->registrationFields()->create([
        'field_key' => 'custom_shirt_size',
        'label' => 'T-Shirt Size',
        'field_type' => 'text',
        'is_system' => false,
        'display_order' => 10,
    ]);

    $this->actingAs($manager)
        ->delete(route('events.registration-fields.destroy', ['event' => $event, 'field' => $field]))
        ->assertRedirect();

    $this->assertDatabaseMissing('event_registration_fields', ['id' => $field->id]);
});

it('prevents deleting a system registration field', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = fieldManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Fielded Event', 'event_date' => now()]);
    $event->ensureSystemRegistrationFields();
    $field = $event->registrationFields()->where('field_key', 'email')->firstOrFail();

    $this->actingAs($manager)
        ->delete(route('events.registration-fields.destroy', ['event' => $event, 'field' => $field]))
        ->assertStatus(422);

    $this->assertDatabaseHas('event_registration_fields', ['id' => $field->id]);
});

it('prevents a manager from managing registration fields on another company event', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $otherCompany = Company::create(['name' => 'Other Co']);
    $manager = fieldManager($company);
    $event = Event::create(['company_id' => $otherCompany->id, 'title' => 'Private Event', 'event_date' => now()]);

    $this->actingAs($manager)
        ->post(route('events.registration-fields.store', $event), [
            'label' => 'T-Shirt Size',
            'field_type' => 'text',
        ])
        ->assertForbidden();
});

it('allows a manager to view the registration qr page for their own event', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = fieldManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'QR Event', 'event_date' => now()]);

    $this->actingAs($manager)
        ->get(route('events.registration-form.print-qr', $event))
        ->assertOk();
});

it('allows a manager to download the registration qr code as svg', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = fieldManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'QR Event', 'event_date' => now()]);

    $response = $this->actingAs($manager)->get(route('events.registration-form.download-qr', $event));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('image/svg+xml');
});

it('prevents a manager from downloading the qr code for another company event', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $otherCompany = Company::create(['name' => 'Other Co']);
    $manager = fieldManager($company);
    $event = Event::create(['company_id' => $otherCompany->id, 'title' => 'Private Event', 'event_date' => now()]);

    $this->actingAs($manager)
        ->get(route('events.registration-form.download-qr', $event))
        ->assertForbidden();
});
