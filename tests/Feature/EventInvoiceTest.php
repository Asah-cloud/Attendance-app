<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\EventAttendeeCharge;
use App\Models\EventRegistration;
use App\Models\Feature;
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

it('saves the invoice without emailing anyone, then sends it to chosen managers with a PDF attached', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Acme Co']);
    $manager = invoiceManager($company);
    $otherManager = invoiceManager($company);
    $admin = invoiceAdmin();
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    invoiceAttendees($event, 2);

    $this->actingAs($manager)->post(route('events.billing.request', $event));
    $this->actingAs($admin)->post(route('events.billing.approve', $event), ['discount' => '0']);

    $charge = EventAttendeeCharge::where('event_id', $event->id)->firstOrFail();
    expect($charge->invoice_emailed_at)->toBeNull();
    Notification::assertNothingSent();

    $this->actingAs($admin)
        ->get(route('events.billing.show', $event))
        ->assertOk()
        ->assertSee('Send to managers')
        ->assertSee($manager->email)
        ->assertSee($otherManager->email);

    $this->actingAs($admin)
        ->post(route('events.billing.invoice.send', $event), ['managers' => [$manager->id]])
        ->assertRedirect(route('events.billing.show', $event));

    expect($charge->fresh()->invoice_emailed_at)->not->toBeNull();

    Notification::assertSentTo($manager, EventInvoiceReady::class, function ($notification) use ($charge) {
        return $notification->charge->is($charge);
    });
    Notification::assertNotSentTo($otherManager, EventInvoiceReady::class);
});

it('prevents a manager from sending the invoice to other managers', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = invoiceManager($company);
    $admin = invoiceAdmin();
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    invoiceAttendees($event, 1);

    $this->actingAs($manager)->post(route('events.billing.request', $event));
    $this->actingAs($admin)->post(route('events.billing.approve', $event), ['discount' => '0']);

    $this->actingAs($manager)
        ->post(route('events.billing.invoice.send', $event), ['managers' => [$manager->id]])
        ->assertForbidden();
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

it('shows Asah Apex Attendance branding above the billed company on the invoice', function () {
    $company = Company::create(['name' => 'Acme Co', 'email' => 'billing@acme.example']);
    $manager = invoiceManager($company);
    $admin = invoiceAdmin();
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    invoiceAttendees($event, 1);

    $this->actingAs($manager)->post(route('events.billing.request', $event));
    $this->actingAs($admin)->post(route('events.billing.approve', $event), ['discount' => '0']);

    $charge = EventAttendeeCharge::where('event_id', $event->id)->firstOrFail();
    $html = view('events.billing.invoice-pdf', [
        'charge' => $charge,
        'event' => $event,
        'company' => $company,
        'platformLogo' => null,
    ])->render();

    expect($html)->toContain('Asah Apex Attendance')
        ->toContain('Billed to')
        ->toContain('Acme Co')
        ->toContain('billing@acme.example');

    // The platform's name appears before the billed company's, in document order.
    expect(strpos($html, 'Asah Apex Attendance'))->toBeLessThan(strpos($html, 'Acme Co'));
});

it('omits the confirmed-attendee line on the invoice when there are none, but shows it otherwise', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $admin = invoiceAdmin();
    $billing = app(EventBillingService::class);

    $emptyEvent = Event::create(['company_id' => $company->id, 'title' => 'Empty Event', 'event_date' => now()->addWeek()]);
    $emptyCharge = $billing->requestInvoice($emptyEvent);
    $billing->approveInvoice($emptyCharge, $admin, [], [], 0, null);

    $peopledEvent = Event::create(['company_id' => $company->id, 'title' => 'Peopled Event', 'event_date' => now()->addWeek()]);
    invoiceAttendees($peopledEvent, 2);
    $peopledCharge = $billing->requestInvoice($peopledEvent);
    $billing->approveInvoice($peopledCharge, $admin, [], [], 0, null);

    $emptyHtml = view('events.billing.invoice-pdf', ['charge' => $emptyCharge->fresh(), 'event' => $emptyEvent, 'company' => $company, 'platformLogo' => null])->render();
    $peopledHtml = view('events.billing.invoice-pdf', ['charge' => $peopledCharge->fresh(), 'event' => $peopledEvent, 'company' => $company, 'platformLogo' => null])->render();

    expect($emptyHtml)->not->toContain('confirmed attendee')
        ->and($peopledHtml)->toContain('2 confirmed attendee(s)');
});

it('lets an admin override a feature\'s price for this invoice only, without changing its catalog price', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = invoiceManager($company);
    $admin = invoiceAdmin();
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    invoiceAttendees($event, 2);

    $this->actingAs($manager)
        ->post(route('events.billing.request', $event), ['features' => ['custom_messages', 'badge_studio']]);

    $messages = Feature::where('key', 'custom_messages')->firstOrFail();
    $badges = Feature::where('key', 'badge_studio')->firstOrFail();
    $catalogMessagesPrice = $messages->cost_minor;

    $this->actingAs($admin)
        ->post(route('events.billing.approve', $event), [
            'features' => ['custom_messages', 'badge_studio'],
            'feature_amounts' => [
                'custom_messages' => '10.00', // discounted for this invoice only
            ],
            'discount' => '0',
        ])
        ->assertRedirect(route('events.billing.show', $event));

    $charge = EventAttendeeCharge::where('event_id', $event->id)->firstOrFail();
    $messagesLine = collect($charge->feature_breakdown)->firstWhere('key', 'custom_messages');
    $badgesLine = collect($charge->feature_breakdown)->firstWhere('key', 'badge_studio');

    expect($messagesLine['cost_minor'])->toBe(1000)
        ->and($badgesLine['cost_minor'])->toBe($badges->cost_minor)
        ->and($charge->features_amount_minor)->toBe(1000 + $badges->cost_minor)
        ->and($charge->amount_minor)->toBe((2 * 200) + 1000 + $badges->cost_minor);

    $this->assertDatabaseHas('event_features', ['event_id' => $event->id, 'feature_key' => 'custom_messages', 'cost_minor' => 1000]);

    // The catalog price itself is untouched.
    expect($messages->fresh()->cost_minor)->toBe($catalogMessagesPrice);
});

it('streams the PDF inline for preview but attaches it for download', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = invoiceManager($company);
    $admin = invoiceAdmin();
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    invoiceAttendees($event, 1);

    $this->actingAs($manager)->post(route('events.billing.request', $event));
    $this->actingAs($admin)->post(route('events.billing.approve', $event), ['discount' => '0']);

    $preview = $this->actingAs($manager)->get(route('events.billing.invoice', ['event' => $event, 'preview' => 1]));
    $preview->assertOk();
    expect($preview->headers->get('content-disposition'))->toContain('inline');

    $download = $this->actingAs($manager)->get(route('events.billing.invoice', $event));
    $download->assertOk();
    expect($download->headers->get('content-disposition'))->toContain('attachment');
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
    $billing->approveInvoice($chargeOne, $admin, [], [], 0, null);

    $eventTwo = Event::create(['company_id' => $company->id, 'title' => 'Two', 'event_date' => now()->addWeek()]);
    invoiceAttendees($eventTwo, 1);
    $chargeTwo = $billing->requestInvoice($eventTwo);
    $billing->approveInvoice($chargeTwo, $admin, [], [], 0, null);

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
        ->assertSee('Save invoice');

    $this->actingAs($admin)
        ->post(route('events.billing.approve', $event), ['discount' => '0'])
        ->assertRedirect(route('events.billing.show', $event));

    $charge->refresh();
    expect($charge->status)->toBe(EventAttendeeCharge::STATUS_PENDING_PAYMENT)
        ->and($charge->reviewed_by)->toBe($admin->id)
        ->and($charge->invoice_number)->not->toBeNull();

    // Saving doesn't email anyone — the admin decides that separately.
    Notification::assertNothingSent();

    $this->actingAs($admin)->post(route('events.billing.invoice.send', $event), ['managers' => [$manager->id]]);
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

    Notification::fake(); // reset past the "bill ready" notice finalize() itself sends

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

    // Still payable, and now downloadable too — nobody has been emailed yet.
    Notification::assertNothingSent();

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

it('offers to edit, not generate, an invoice that already has one', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $admin = invoiceAdmin();
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    invoiceAttendees($event, 1);

    $billing = app(EventBillingService::class);
    $charge = $billing->finalize($event);
    $billing->approveInvoice($charge, $admin, [], [], 0, null);

    $this->actingAs($admin)
        ->get(route('events.billing.show', $event))
        ->assertOk()
        ->assertDontSee('No formal invoice yet')
        ->assertSee('Edit this invoice')
        ->assertSee('Edit invoice', false);
});

it('lets an admin edit an already-saved invoice, keeping the same invoice number', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = invoiceManager($company);
    $admin = invoiceAdmin();
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    invoiceAttendees($event, 2);

    $this->actingAs($manager)
        ->post(route('events.billing.request', $event), ['features' => ['custom_messages']]);
    $this->actingAs($admin)
        ->post(route('events.billing.approve', $event), ['features' => ['custom_messages'], 'discount' => '0']);

    $charge = EventAttendeeCharge::where('event_id', $event->id)->firstOrFail();
    $originalInvoiceNumber = $charge->invoice_number;
    $messages = Feature::where('key', 'custom_messages')->firstOrFail();

    $badges = Feature::where('key', 'badge_studio')->firstOrFail();

    // Edit it later: drop custom_messages, add badge_studio, apply a discount.
    $this->actingAs($admin)
        ->post(route('events.billing.approve', $event), [
            'features' => ['badge_studio'],
            'discount' => '3.00',
            'discount_reason' => 'Adjusted after the fact',
        ])
        ->assertRedirect(route('events.billing.show', $event));

    $charge->refresh();
    expect($charge->invoice_number)->toBe($originalInvoiceNumber)
        ->and($charge->status)->toBe(EventAttendeeCharge::STATUS_PENDING_PAYMENT)
        ->and($charge->discount_minor)->toBe(300)
        ->and($charge->discount_reason)->toBe('Adjusted after the fact')
        ->and(collect($charge->feature_breakdown)->pluck('key')->all())->toBe(['badge_studio'])
        ->and($charge->amount_minor)->toBe((2 * 200) + $badges->cost_minor - 300);

    $this->assertDatabaseMissing('event_features', ['event_id' => $event->id, 'feature_key' => 'custom_messages']);
    $this->assertDatabaseHas('event_features', ['event_id' => $event->id, 'feature_key' => 'badge_studio']);
});

it('flags that an invoice was edited after it was already sent', function () {
    Notification::fake();
    $company = Company::create(['name' => 'Acme Co']);
    $manager = invoiceManager($company);
    $admin = invoiceAdmin();
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    invoiceAttendees($event, 1);

    $this->actingAs($manager)->post(route('events.billing.request', $event));
    $this->actingAs($admin)->post(route('events.billing.approve', $event), ['discount' => '0']);
    $this->actingAs($admin)->post(route('events.billing.invoice.send', $event), ['managers' => [$manager->id]]);

    $charge = EventAttendeeCharge::where('event_id', $event->id)->firstOrFail();
    expect($charge->hasUnsentChanges())->toBeFalse();

    $this->travel(1)->minute();
    $this->actingAs($admin)->post(route('events.billing.approve', $event), ['discount' => '2.00', 'discount_reason' => 'Late adjustment']);

    $charge->refresh();
    expect($charge->hasUnsentChanges())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('events.billing.show', $event))
        ->assertOk()
        ->assertSee('edited after it was last sent');
});

it('blocks editing an invoice once it has been paid', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = invoiceManager($company);
    $admin = invoiceAdmin();
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    invoiceAttendees($event, 1);

    $billing = app(EventBillingService::class);
    $charge = $billing->requestInvoice($event);
    $billing->approveInvoice($charge, $admin, [], [], 0, null);
    $billing->confirmPayment($charge->fresh());

    $this->actingAs($admin)
        ->get(route('events.billing.show', $event))
        ->assertOk()
        ->assertDontSee('Edit this invoice')
        ->assertDontSee('Edit invoice', false);

    $this->actingAs($admin)
        ->post(route('events.billing.approve', $event), ['discount' => '0'])
        ->assertNotFound();
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
