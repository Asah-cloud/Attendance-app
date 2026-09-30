<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\EventAttendeeCharge;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\EventInvoiceReady;
use App\Services\EventBillingService;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function invoiceManager(Company $company): User
{
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    return $manager;
}

function invoiceAdmin(): User
{
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');

    return $admin;
}

function invoiceAttendees(Event $event, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        $participant = Participant::create([
            'company_id' => $event->company_id,
            'name' => "Attendee {$i}",
            'email' => "attendee{$i}-{$event->id}@example.invalid",
        ]);
        EventRegistration::create([
            'event_id' => $event->id,
            'participant_id' => $participant->id,
            'status' => EventRegistration::STATUS_CONFIRMED,
        ]);
    }
}

it('emails the invoice with a PDF attached once an admin approves it', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Acme Co']);
    $manager = invoiceManager($company);
    $admin = invoiceAdmin();
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    invoiceAttendees($event, 2);

    $this->actingAs($manager)->post(route('events.billing.request', $event));
    $this->actingAs($admin)->post(route('events.billing.approve', $event), ['discount' => '0']);

    $charge = EventAttendeeCharge::where('event_id', $event->id)->firstOrFail();
    expect($charge->invoice_emailed_at)->not->toBeNull();

    Notification::assertSentTo($manager, EventInvoiceReady::class, function ($notification, $channels) use ($charge) {
        return $notification->charge->is($charge);
    });
});

it('lets a manager download the invoice PDF once approved, but not while awaiting review', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = invoiceManager($company);
    $admin = invoiceAdmin();
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    invoiceAttendees($event, 2);

    $this->actingAs($manager)->post(route('events.billing.request', $event));

    $this->actingAs($manager)
        ->get(route('events.billing.invoice', $event))
        ->assertNotFound();

    $this->actingAs($admin)->post(route('events.billing.approve', $event), ['discount' => '0']);

    $this->actingAs($manager)
        ->get(route('events.billing.invoice', $event))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('lets an admin preview the invoice PDF while it is still awaiting review', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = invoiceManager($company);
    $admin = invoiceAdmin();
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    invoiceAttendees($event, 2);

    $this->actingAs($manager)->post(route('events.billing.request', $event));

    $this->actingAs($admin)
        ->get(route('events.billing.invoice', $event))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('lets a manager resend the invoice email on demand', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Acme Co']);
    $manager = invoiceManager($company);
    $admin = invoiceAdmin();
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    invoiceAttendees($event, 1);

    $this->actingAs($manager)->post(route('events.billing.request', $event));
    $this->actingAs($admin)->post(route('events.billing.approve', $event), ['discount' => '0']);

    Notification::fake();

    $this->actingAs($manager)
        ->post(route('events.billing.invoice.email', $event))
        ->assertRedirect(route('events.billing.show', $event));

    Notification::assertSentTo($manager, EventInvoiceReady::class);
});

it('requires a discount reason when a discount amount is entered', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = invoiceManager($company);
    $admin = invoiceAdmin();
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    invoiceAttendees($event, 1);

    $this->actingAs($manager)->post(route('events.billing.request', $event));

    $this->actingAs($admin)
        ->post(route('events.billing.approve', $event), ['discount' => '5.00'])
        ->assertSessionHasErrors('discount_reason');

    expect(EventAttendeeCharge::where('event_id', $event->id)->value('status'))->toBe(EventAttendeeCharge::STATUS_PENDING_REVIEW);
});

it('generates sequential invoice numbers', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $admin = invoiceAdmin();
    $billing = app(EventBillingService::class);

    $eventOne = Event::create(['company_id' => $company->id, 'title' => 'One', 'event_date' => now()->addWeek()]);
    invoiceAttendees($eventOne, 1);
    $chargeOne = $billing->requestInvoice($eventOne);
    $billing->approveInvoice($chargeOne, $admin, [], 0, null);

    $eventTwo = Event::create(['company_id' => $company->id, 'title' => 'Two', 'event_date' => now()->addWeek()]);
    invoiceAttendees($eventTwo, 1);
    $chargeTwo = $billing->requestInvoice($eventTwo);
    $billing->approveInvoice($chargeTwo, $admin, [], 0, null);

    expect($chargeOne->fresh()->invoice_number)->not->toBe($chargeTwo->fresh()->invoice_number);
});

it('lets an admin generate and approve an invoice themselves, without a manager involved', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Acme Co']);
    $manager = invoiceManager($company); // never acts — invoice is entirely admin-driven
    $admin = invoiceAdmin();
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    invoiceAttendees($event, 2);

    $this->actingAs($admin)
        ->get(route('events.billing.show', $event))
        ->assertOk()
        ->assertSee('Generate invoice')
        ->assertDontSee('Request invoice');

    $this->actingAs($admin)
        ->post(route('events.billing.request', $event))
        ->assertRedirect(route('events.billing.show', $event));

    $charge = EventAttendeeCharge::where('event_id', $event->id)->firstOrFail();
    expect($charge->status)->toBe(EventAttendeeCharge::STATUS_PENDING_REVIEW);

    // The same admin reviews and approves their own generated invoice.
    $this->actingAs($admin)
        ->get(route('events.billing.show', $event))
        ->assertOk()
        ->assertSee('Approve & send invoice', false);

    $this->actingAs($admin)
        ->post(route('events.billing.approve', $event), ['discount' => '0'])
        ->assertRedirect(route('events.billing.show', $event));

    $charge->refresh();
    expect($charge->status)->toBe(EventAttendeeCharge::STATUS_PENDING_PAYMENT)
        ->and($charge->reviewed_by)->toBe($admin->id)
        ->and($charge->invoice_number)->not->toBeNull();

    Notification::assertSentTo($manager, EventInvoiceReady::class);
});

it('lets an admin generate a formal invoice for a bill that was already created automatically, without blocking its payability', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Acme Co']);
    $manager = invoiceManager($company);
    $admin = invoiceAdmin();
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->subDay()]);
    invoiceAttendees($event, 3);

    // Simulates the automatic end-of-event fallback: instantly payable, no review.
    $charge = app(EventBillingService::class)->finalize($event);
    expect($charge->status)->toBe(EventAttendeeCharge::STATUS_PENDING_PAYMENT)
        ->and($charge->invoice_number)->toBeNull()
        ->and($charge->needsInvoice())->toBeTrue();

    // The manager can already pay it — nothing changed for them yet.
    $this->actingAs($manager)
        ->get(route('events.billing.show', $event))
        ->assertOk()
        ->assertSee('Pay with Paystack');

    // The admin sees the option to generate a formal invoice for it.
    $this->actingAs($admin)
        ->get(route('events.billing.show', $event))
        ->assertOk()
        ->assertSee('Generate invoice')
        ->assertSee('No formal invoice yet');

    $this->actingAs($admin)
        ->post(route('events.billing.approve', $event), ['discount' => '0'])
        ->assertRedirect(route('events.billing.show', $event));

    $charge->refresh();
    expect($charge->status)->toBe(EventAttendeeCharge::STATUS_PENDING_PAYMENT)
        ->and($charge->invoice_number)->not->toBeNull()
        ->and($charge->needsInvoice())->toBeFalse();

    Notification::assertSentTo($manager, EventInvoiceReady::class);

    // Still payable, and now downloadable too.
    $this->actingAs($manager)
        ->get(route('events.billing.show', $event))
        ->assertOk()
        ->assertSee('Pay with Paystack')
        ->assertSee('Download invoice PDF');

    $this->actingAs($manager)
        ->get(route('events.billing.invoice', $event))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('does not offer to generate an invoice for a bill that already has one', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $admin = invoiceAdmin();
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    invoiceAttendees($event, 1);

    $billing = app(EventBillingService::class);
    $charge = $billing->finalize($event);
    $billing->approveInvoice($charge, $admin, [], 0, null);

    $this->actingAs($admin)
        ->get(route('events.billing.show', $event))
        ->assertOk()
        ->assertDontSee('No formal invoice yet');
});

it('voids a pending-review invoice request when the event is cancelled', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = invoiceManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    invoiceAttendees($event, 1);

    $this->actingAs($manager)->post(route('events.billing.request', $event));
    $this->actingAs($manager)->patch(route('events.cancel', $event));

    expect(EventAttendeeCharge::where('event_id', $event->id)->value('status'))->toBe(EventAttendeeCharge::STATUS_VOIDED);
});
