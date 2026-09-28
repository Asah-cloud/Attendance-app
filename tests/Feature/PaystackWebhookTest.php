<?php

use App\Models\Company;
use App\Models\Event;
use App\Models\EventAttendeeCharge;

function signedPaystackWebhookHeaders(string $body, string $secret): array
{
    return ['x-paystack-signature' => hash_hmac('sha512', $body, $secret)];
}

function paystackWebhookBody(string $reference): string
{
    return json_encode([
        'event' => 'charge.success',
        'data' => ['reference' => $reference, 'status' => 'success', 'amount' => 9900, 'currency' => 'GHS'],
    ]);
}

beforeEach(function () {
    config()->set('services.paystack.secret_key', 'sk_test_webhooksecret');
});

it('rejects a paystack webhook call with a missing or invalid signature', function () {
    $response = $this->postJson(route('paystack.webhook'), ['event' => 'charge.success']);

    $response->assertForbidden();
});

it('rejects a paystack webhook call with a wrong signature', function () {
    $body = paystackWebhookBody('SUB-1-ABC');

    $response = $this->call('POST', route('paystack.webhook'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => 'not-the-real-signature',
    ], content: $body);

    $response->assertForbidden();
});

it('ignores a legacy subscription reference now that subscriptions are retired', function () {
    $body = paystackWebhookBody('SUB-1-ABCDEFGHIJKLMNOP');
    $headers = signedPaystackWebhookHeaders($body, 'sk_test_webhooksecret');

    $this->call('POST', route('paystack.webhook'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => $headers['x-paystack-signature'],
    ], content: $body)->assertOk();
});

it('confirms a pending event attendee charge for a correctly signed webhook', function () {
    $company = Company::create(['name' => 'Event Billing Co', 'is_active' => true]);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Webhook Conference', 'event_date' => now()->addWeek()]);
    $charge = EventAttendeeCharge::create([
        'event_id' => $event->id,
        'company_id' => $company->id,
        'status' => EventAttendeeCharge::STATUS_PENDING_PAYMENT,
        'registered_count' => 10,
        'tier_breakdown' => [],
        'amount_minor' => 5000,
        'currency' => 'GHS',
        'payment_reference' => 'EVB-'.$event->id.'-ABCDEFGHIJKLMNOP',
        'finalized_at' => now(),
    ]);

    $body = paystackWebhookBody($charge->payment_reference);
    $headers = signedPaystackWebhookHeaders($body, 'sk_test_webhooksecret');

    $this->call('POST', route('paystack.webhook'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => $headers['x-paystack-signature'],
    ], content: $body)->assertOk();

    $charge->refresh();
    expect($charge->status)->toBe(EventAttendeeCharge::STATUS_PAID)
        ->and($charge->paid_at)->not->toBeNull();
});

it('logs and ignores an onboarding reference with no matching account yet', function () {
    $body = paystackWebhookBody('ONB-NOACCOUNTYET');
    $headers = signedPaystackWebhookHeaders($body, 'sk_test_webhooksecret');

    $this->call('POST', route('paystack.webhook'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => $headers['x-paystack-signature'],
    ], content: $body)->assertOk();
});
