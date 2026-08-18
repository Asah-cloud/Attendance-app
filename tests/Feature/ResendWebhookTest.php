<?php

use Illuminate\Support\Facades\Event;
use Resend\Laravel\Events\EmailBounced;

function signedResendWebhookHeaders(string $body, string $secret): array
{
    $secretBytes = base64_decode(substr($secret, strlen('whsec_')));
    $messageId = 'msg_test';
    $timestamp = (string) time();
    $toSign = "{$messageId}.{$timestamp}.{$body}";
    $signature = 'v1,'.base64_encode(pack('H*', hash_hmac('sha256', $toSign, $secretBytes)));

    return [
        'svix-id' => $messageId,
        'svix-timestamp' => $timestamp,
        'svix-signature' => $signature,
    ];
}

it('rejects a resend webhook call with a missing or invalid signature', function () {
    config()->set('resend.webhook.secret', 'whsec_'.base64_encode(random_bytes(32)));

    $response = $this->postJson(route('resend.webhook'), ['type' => 'email.bounced']);

    $response->assertForbidden();
});

it('dispatches an event for a correctly signed resend webhook call', function () {
    Event::fake([EmailBounced::class]);
    $secret = 'whsec_'.base64_encode(random_bytes(32));
    config()->set('resend.webhook.secret', $secret);

    $body = json_encode([
        'type' => 'email.bounced',
        'data' => ['email_id' => 'abc123', 'to' => ['participant@example.com'], 'subject' => 'Event reminder'],
    ]);

    $headers = signedResendWebhookHeaders($body, $secret);

    $response = $this->call('POST', route('resend.webhook'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_SVIX_ID' => $headers['svix-id'],
        'HTTP_SVIX_TIMESTAMP' => $headers['svix-timestamp'],
        'HTTP_SVIX_SIGNATURE' => $headers['svix-signature'],
    ], content: $body);

    $response->assertOk();
    Event::assertDispatched(EmailBounced::class);
});
