<?php

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventAttendeeCharge;
use App\Models\Feature;
use App\Services\EventBillingService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
});

it('lists the seeded advanced features on the public pricing page', function () {
    $this->get(route('pricing'))
        ->assertOk()
        ->assertSee('Custom Messages (Email & SMS)')
        ->assertSee('Badge / ID Studio');
});

it('shows selectable features on the billing show page before finalizing', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = attendeeBillingManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    registerConfirmedAttendees($event, 2);

    $this->actingAs($manager)
        ->get(route('events.billing.show', $event))
        ->assertOk()
        ->assertSee('Advanced features')
        ->assertSee('Custom Messages (Email & SMS)');
});

it('lets a manager buy advanced features when finalizing a bill and locks in the price', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = attendeeBillingManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    registerConfirmedAttendees($event, 3);

    $messages = Feature::where('key', 'custom_messages')->firstOrFail();
    $badges = Feature::where('key', 'badge_studio')->firstOrFail();

    $this->actingAs($manager)
        ->post(route('events.billing.finalize', $event), ['features' => ['custom_messages', 'badge_studio']])
        ->assertRedirect(route('events.billing.show', $event));

    $charge = EventAttendeeCharge::where('event_id', $event->id)->firstOrFail();
    $attendeeAmount = 3 * 200;
    $featuresAmount = $messages->cost_minor + $badges->cost_minor;

    expect($charge->amount_minor)->toBe($attendeeAmount + $featuresAmount)
        ->and($charge->features_amount_minor)->toBe($featuresAmount)
        ->and($charge->feature_breakdown)->toHaveCount(2);

    $this->assertDatabaseHas('event_features', ['event_id' => $event->id, 'feature_key' => 'custom_messages']);
    $this->assertDatabaseHas('event_features', ['event_id' => $event->id, 'feature_key' => 'badge_studio']);
    $this->assertDatabaseMissing('event_features', ['event_id' => $event->id, 'feature_key' => 'rooms']);
});

it('ignores inactive or unknown feature keys submitted to finalize', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $manager = attendeeBillingManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    registerConfirmedAttendees($event, 1);

    Feature::where('key', 'rooms')->update(['is_active' => false]);

    $this->actingAs($manager)
        ->post(route('events.billing.finalize', $event), ['features' => ['rooms', 'not-a-real-key']]);

    $charge = EventAttendeeCharge::where('event_id', $event->id)->firstOrFail();
    expect($charge->features_amount_minor)->toBe(0);
    $this->assertDatabaseMissing('event_features', ['event_id' => $event->id]);
});

it('blocks a paid feature route until that feature is bought and paid for, then allows it', function () {
    fakePaystackForEventBilling();
    $company = Company::create(['name' => 'Acme Co']);
    $manager = attendeeBillingManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    registerConfirmedAttendees($event, 1);

    // No bill at all yet.
    $this->actingAs($manager)
        ->get(route('events.messages.index', $event))
        ->assertRedirect(route('events.billing.show', $event));

    $billing = app(EventBillingService::class);
    $charge = $billing->finalize($event, ['badge_studio']); // bought badges, not messages

    $this->actingAs($manager)
        ->get(route('events.messages.index', $event))
        ->assertRedirect(route('events.billing.show', $event));

    // Even once paid, an un-purchased feature stays blocked.
    $billing->confirmPayment($charge->fresh());
    $this->actingAs($manager)
        ->get(route('events.messages.index', $event))
        ->assertRedirect(route('events.billing.show', $event));

    // The feature that was actually bought and paid for now works.
    $this->actingAs($manager)
        ->get(route('events.badges', $event))
        ->assertOk();
});

it('lets an admin bypass feature gating', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $admin = attendeeBillingAdmin();
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    registerConfirmedAttendees($event, 1);

    $this->actingAs($admin)
        ->get(route('events.messages.index', $event))
        ->assertOk();
});

it('never gates the basic report view or exports the advanced_reports feature', function () {
    fakePaystackForEventBilling();
    $company = Company::create(['name' => 'Acme Co']);
    $manager = attendeeBillingManager($company);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    registerConfirmedAttendees($event, 1);

    $this->actingAs($manager)->get(route('reports.event', $event))->assertOk();
    $this->actingAs($manager)->get(route('reports.excel', ['event' => $event, 'day' => 1]))->assertRedirect(route('events.billing.show', $event));

    $billing = app(EventBillingService::class);
    $charge = $billing->finalize($event, ['advanced_reports']);
    $billing->confirmPayment($charge->fresh());

    $this->actingAs($manager)->get(route('reports.excel', ['event' => $event, 'day' => 1]))->assertOk();
});

it('excludes feature cost from the attendee no-show refund at reconciliation', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Past Conference', 'event_date' => now()->subDays(3), 'end_date' => now()->subDay()]);
    registerConfirmedAttendees($event, 2);
    $participants = $event->registrations()->pluck('participant_id');

    Attendance::create(['event_id' => $event->id, 'participant_id' => $participants->first(), 'day' => 1, 'status' => 'present']);

    $billing = app(EventBillingService::class);
    $charge = $billing->finalize($event, ['custom_messages']);
    $billing->confirmPayment($charge->fresh());
    $billing->reconcile($charge->fresh());

    $charge->refresh();
    // 1 no-show @ GHS 2.00 = 200 minor units. Feature cost is not refunded.
    expect($charge->refund_amount_minor)->toBe(200)
        ->and($charge->features_amount_minor)->toBe(Feature::where('key', 'custom_messages')->value('cost_minor'));
});

it('lets a super admin manage the advanced feature catalog', function () {
    $admin = attendeeBillingAdmin();

    $this->actingAs($admin)->get(route('pricing.features.index'))->assertOk()->assertSee('Custom Messages');

    $this->actingAs($admin)
        ->post(route('pricing.features.store'), [
            'name' => 'Live Streaming',
            'cost' => '25.00',
            'description' => 'Stream the event live.',
            'is_active' => '1',
        ])
        ->assertRedirect(route('pricing.features.index'));

    $this->assertDatabaseHas('features', ['key' => 'live_streaming', 'cost_minor' => 2500, 'tier' => 'advanced']);

    $feature = Feature::where('key', 'live_streaming')->firstOrFail();

    $this->actingAs($admin)
        ->put(route('pricing.features.update', $feature), [
            'name' => $feature->name,
            'cost' => '30.00',
            'is_active' => '0',
        ])
        ->assertRedirect(route('pricing.features.index'));

    expect($feature->fresh()->cost_minor)->toBe(3000)
        ->and($feature->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)
        ->delete(route('pricing.features.destroy', $feature))
        ->assertRedirect(route('pricing.features.index'));

    $this->assertDatabaseMissing('features', ['id' => $feature->id]);
});

it('refuses to delete a feature that an event has already bought', function () {
    $company = Company::create(['name' => 'Acme Co']);
    $admin = attendeeBillingAdmin();
    $event = Event::create(['company_id' => $company->id, 'title' => 'Conference', 'event_date' => now()->addWeek()]);
    registerConfirmedAttendees($event, 1);
    app(EventBillingService::class)->finalize($event, ['rooms']);

    $feature = Feature::where('key', 'rooms')->firstOrFail();

    $this->actingAs($admin)
        ->delete(route('pricing.features.destroy', $feature))
        ->assertRedirect();

    $this->assertDatabaseHas('features', ['id' => $feature->id]);
});
