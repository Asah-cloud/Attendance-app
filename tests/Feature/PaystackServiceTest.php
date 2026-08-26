<?php

use App\Services\PaystackService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.paystack', [
        'secret_key' => 'sk_test_123',
        'public_key' => 'pk_test_123',
        'base_url' => 'https://paystack.test',
    ]);
});

it('initializes a transaction with the raw minor-unit amount and a bearer token', function () {
    Http::fake(['paystack.test/*' => Http::response([
        'status' => true,
        'data' => ['authorization_url' => 'https://paystack.test/pay/abc', 'reference' => 'SUB-1-ABC'],
    ], 200)]);

    $data = app(PaystackService::class)->initialize(
        amountMinor: 9900,
        currency: 'GHS',
        email: 'manager@example.com',
        reference: 'SUB-1-ABC',
        callbackUrl: 'https://app.test/billing/checkout/callback',
        metadata: ['flow' => 'subscription', 'company_id' => 1],
    );

    expect($data['authorization_url'])->toBe('https://paystack.test/pay/abc');

    Http::assertSent(fn ($request) => $request->url() === 'https://paystack.test/transaction/initialize'
        && $request->hasHeader('Authorization', 'Bearer sk_test_123')
        && $request['amount'] === 9900
        && $request['currency'] === 'GHS'
        && $request['email'] === 'manager@example.com'
        && $request['reference'] === 'SUB-1-ABC'
        && $request['metadata']['flow'] === 'subscription');
});

it('verifies a transaction by reference', function () {
    Http::fake(['paystack.test/*' => Http::response([
        'status' => true,
        'data' => ['status' => 'success', 'amount' => 9900, 'currency' => 'GHS', 'reference' => 'SUB-1-ABC'],
    ], 200)]);

    $data = app(PaystackService::class)->verify('SUB-1-ABC');

    expect($data['status'])->toBe('success')
        ->and($data['amount'])->toBe(9900);

    Http::assertSent(fn ($request) => $request->url() === 'https://paystack.test/transaction/verify/SUB-1-ABC'
        && $request->method() === 'GET');
});

it('throws when Paystack rejects the initialize request', function () {
    Http::fake(['paystack.test/*' => Http::response(['status' => false, 'message' => 'Invalid key'], 401)]);

    app(PaystackService::class)->initialize(9900, 'GHS', 'a@b.com', 'SUB-1-ABC', 'https://app.test/callback');
})->throws(RuntimeException::class);
