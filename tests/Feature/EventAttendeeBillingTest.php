<?php

use App\Models\Attendance;
use App\Models\AttendeePricingTier;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventAttendeeCharge;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\User;
use App\Services\AttendeePricingResolver;
use App\Services\AttendeePricingTierParser;
use App\Services\EventBillingService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

function attendeeBillingManager(Company $company): User
{
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');

    return $manager;
}

function attendeeBillingAdmin(): User
{
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');

    return $admin;
}

function registerConfirmedAttendees(Event $event, int $count): void
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

it('computes a graduated total across multiple platform-default bands', function () {
    $company = Company::create(['name' => 'Acme Co']);

    $calc = app(AttendeePricingResolver::class)->calculate($company, 450);

    // Seeded platform tiers: 0-100@200, 100-300@150, 300-500@100, 500-1000@75, 1000-@50
    expect($calc['amount_minor'])->toBe(100 * 200 + 200 * 150 + 150 * 100)
        ->and($calc['breakdown'])->toHaveCount(3);
});

it('lets a company-level override beat both plan and platform tiers', function () {
    $company = Company::create(['name' => 'Acme Co', 'plan_key' => 'starter']);
    AttendeePricingTier::create(['scope_type' => 'plan', 'plan_key' => 'starter', 'band_from' => 0, 'band_to' => null, 'rate_minor' => 500]);
    AttendeePricingTier::create(['scope_type' => 'company', 'company_id' => $company->id, 'band_from' => 0, 'band_to' => null, 'rate_minor' => 10]);

    $calc = app(AttendeePricingResolver::class)->calculate($company, 450);

    expect($calc['amount_minor'])->toBe(450 * 10);
});

it('lets a manager finalize and pay an event attendee bill', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = attendeeBillingManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Annual Conference', 'event_date' => now()->addWeek()]);
    registerConfirmedAttendees($event, 3);

    $this->actingAs($manager)
        ->get(route('events.billing.show', $event))
        ->assertOk()
        ->assertSee('Estimated attendee bill');

    $this->actingAs($manager)
        ->post(route('events.billing.finalize', $event))
        ->assertRedirect(route('events.billing.show', $event));

    $this->assertDatabaseHas('event_attendee_charges', [
        'event_id' => $event->id,
        'status' => EventAttendeeCharge::STATUS_PENDING_PAYMENT,
        'registered_count' => 3,
        'amount_minor' => 3 * 200,
    ]);

    $this->actingAs($manager)
        ->post(route('events.billing.pay', $event))
        ->assertRedirect(route('events.billing.show', $event));

    $charge = EventAttendeeCharge::where('event_id', $event->id)->firstOrFail();
    expect($charge->status)->toBe(EventAttendeeCharge::STATUS_PAID)
        ->and($charge->payment_reference)->not->toBeNull();
});

it('reconciles a paid bill and computes a refund for no-shows once the event has closed', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Past Conference', 'event_date' => now()->subDays(3), 'end_date' => now()->subDay()]);
    registerConfirmedAttendees($event, 3);
    $participants = $event->registrations()->pluck('participant_id');

    foreach ($participants->take(2) as $participantId) {
        Attendance::create(['event_id' => $event->id, 'participant_id' => $participantId, 'day' => 1, 'status' => 'present']);
    }

    $billing = app(EventBillingService::class);
    $charge = $billing->finalize($event);
    $billing->pay($charge->fresh());
    $billing->reconcile($charge->fresh());

    $charge->refresh();
    expect($charge->status)->toBe(EventAttendeeCharge::STATUS_REFUND_DUE)
        ->and($charge->checked_in_count)->toBe(2)
        ->and($charge->refund_amount_minor)->toBe(200); // 1 no-show @ GHS 2.00
});

it('reconciles with zero refund when every registrant checked in', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Past Conference', 'event_date' => now()->subDays(3), 'end_date' => now()->subDay()]);
    registerConfirmedAttendees($event, 2);
    foreach ($event->registrations()->pluck('participant_id') as $participantId) {
        Attendance::create(['event_id' => $event->id, 'participant_id' => $participantId, 'day' => 1, 'status' => 'present']);
    }

    $billing = app(EventBillingService::class);
    $charge = $billing->finalize($event);
    $billing->pay($charge->fresh());
    $billing->reconcile($charge->fresh());

    $charge->refresh();
    expect($charge->status)->toBe(EventAttendeeCharge::STATUS_RECONCILED)
        ->and($charge->refund_amount_minor)->toBe(0);
});

it('lets a super admin mark a refund-due charge as refunded', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $admin = attendeeBillingAdmin();
    $event = Event::create(['company_id' => $company->id, 'title' => 'Past Conference', 'event_date' => now()->subWeek()]);
    registerConfirmedAttendees($event, 1);
    $billing = app(EventBillingService::class);
    $charge = $billing->finalize($event);
    $billing->pay($charge->fresh());
    $billing->voidForCancellation($event); // event never happened -> full refund due

    $this->actingAs($admin)
        ->get(route('attendee-billing.index'))
        ->assertOk()
        ->assertSee('Acme Co');

    $this->actingAs($admin)
        ->post(route('attendee-billing.refund', $charge))
        ->assertRedirect();

    expect($charge->fresh()->status)->toBe(EventAttendeeCharge::STATUS_REFUNDED);
});

it('voids an unpaid bill on cancellation and fully refunds a paid one', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = attendeeBillingManager($company);

    $unpaidEvent = Event::create(['company_id' => $company->id, 'title' => 'Unpaid Event', 'event_date' => now()->addWeek()]);
    registerConfirmedAttendees($unpaidEvent, 1);
    app(EventBillingService::class)->finalize($unpaidEvent);

    $this->actingAs($manager)->patch(route('events.cancel', $unpaidEvent))->assertRedirect();
    expect(EventAttendeeCharge::where('event_id', $unpaidEvent->id)->first()->status)->toBe(EventAttendeeCharge::STATUS_VOIDED);

    $paidEvent = Event::create(['company_id' => $company->id, 'title' => 'Paid Event', 'event_date' => now()->addWeek()]);
    registerConfirmedAttendees($paidEvent, 1);
    $billing = app(EventBillingService::class);
    $charge = $billing->finalize($paidEvent);
    $billing->pay($charge->fresh());

    $this->actingAs($manager)->patch(route('events.cancel', $paidEvent))->assertRedirect();
    $charge->refresh();
    expect($charge->status)->toBe(EventAttendeeCharge::STATUS_REFUND_DUE)
        ->and($charge->refund_amount_minor)->toBe($charge->amount_minor);
});

it('prevents an usher and a cross-company manager from reaching event billing', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $otherCompany = Company::create(['name' => 'Other Co']);
    $usher = User::factory()->create(['company_id' => $company->id, 'role' => 'usher']);
    $usher->assignRole('usher');
    $outsider = attendeeBillingManager($otherCompany);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Annual Conference', 'event_date' => now()->addWeek()]);

    $this->actingAs($usher)->get(route('events.billing.show', $event))->assertForbidden();
    $this->actingAs($outsider)->get(route('events.billing.show', $event))->assertForbidden();
});

it('rejects a non-contiguous tier table and one missing an unbounded last line', function () {
    $parser = app(AttendeePricingTierParser::class);

    expect(fn () => $parser->parse("0-100:2.00\n150-300:1.50\n300-:1.00"))
        ->toThrow(ValidationException::class);

    expect(fn () => $parser->parse("0-100:2.00\n100-300:1.50"))
        ->toThrow(ValidationException::class);
});

it('lets a super admin edit platform and per-company attendee pricing', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $admin = attendeeBillingAdmin();

    $this->actingAs($admin)
        ->put(route('attendee-pricing.platform.update'), ['tiers' => "0-100:3.00\n100-:1.00"])
        ->assertRedirect();

    $this->assertDatabaseHas('attendee_pricing_tiers', ['scope_type' => 'platform', 'band_from' => 0, 'band_to' => 100, 'rate_minor' => 300]);
    $this->assertDatabaseMissing('attendee_pricing_tiers', ['scope_type' => 'platform', 'band_from' => 500]);

    $this->actingAs($admin)
        ->put(route('companies.update', $company), [
            'name' => $company->name,
            'email' => $company->email,
            'billing_mode' => Company::BILLING_MODE_SUBSCRIPTION,
            'subscription_ends_at' => now()->addYear()->toDateString(),
            'event_limit' => 5,
            'is_active' => 1,
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->put(route('pricing.companies.update', $company), ['tiers' => '0-:5.00'])
        ->assertRedirect();

    $this->assertDatabaseHas('attendee_pricing_tiers', ['scope_type' => 'company', 'company_id' => $company->id, 'band_from' => 0, 'band_to' => null, 'rate_minor' => 500]);
});

it('lets a super admin set and clear a per-event pricing override that beats company pricing', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $admin = attendeeBillingAdmin();
    $event = Event::create(['company_id' => $company->id, 'title' => 'Annual Conference', 'event_date' => now()->addWeek()]);
    AttendeePricingTier::create(['scope_type' => 'company', 'company_id' => $company->id, 'band_from' => 0, 'band_to' => null, 'rate_minor' => 500]);

    $this->actingAs($admin)->get(route('pricing.companies.index'))->assertOk()->assertSee('Acme Co');
    $this->actingAs($admin)->get(route('pricing.companies.show', $company))->assertOk()->assertSee('Annual Conference');
    $this->actingAs($admin)->get(route('pricing.companies.events.edit', [$company, $event]))->assertOk();

    expect(app(AttendeePricingResolver::class)->calculate($company, 10, $event)['amount_minor'])->toBe(10 * 500);

    $this->actingAs($admin)
        ->put(route('pricing.companies.events.update', [$company, $event]), ['tiers' => '0-:10.00'])
        ->assertRedirect(route('pricing.companies.show', $company));

    $this->assertDatabaseHas('attendee_pricing_tiers', ['scope_type' => 'event', 'event_id' => $event->id, 'rate_minor' => 1000]);
    expect(app(AttendeePricingResolver::class)->calculate($company, 10, $event)['amount_minor'])->toBe(10 * 1000)
        ->and(app(AttendeePricingResolver::class)->calculate($company, 10)['amount_minor'])->toBe(10 * 500);

    $this->actingAs($admin)
        ->put(route('pricing.companies.events.update', [$company, $event]), ['tiers' => ''])
        ->assertRedirect(route('pricing.companies.show', $company));

    $this->assertDatabaseMissing('attendee_pricing_tiers', ['scope_type' => 'event', 'event_id' => $event->id]);
    expect(app(AttendeePricingResolver::class)->calculate($company, 10, $event)['amount_minor'])->toBe(10 * 500);
});

it('lets a pay-per-event company create unlimited events with no subscription gate', function () {
    $company = Company::create(['name' => 'Acme Co', 'billing_mode' => Company::BILLING_MODE_PAY_PER_EVENT]);
    $manager = attendeeBillingManager($company);

    for ($i = 0; $i < $company->event_limit + 2; $i++) {
        $this->actingAs($manager)
            ->post(route('events.store'), [
                'company_id' => $company->id,
                'title' => "Event {$i}",
                'event_date' => now()->addWeek()->toDateString(),
            ])
            ->assertRedirect('/events');
    }

    expect($company->events()->count())->toBe($company->event_limit + 2);

    $this->actingAs($manager)->get('/dashboard')->assertOk();
});
