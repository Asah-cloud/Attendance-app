<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackService
{
    /**
     * Start a Paystack transaction. Returns Paystack's `data` payload
     * (notably `authorization_url` and `reference`) on success.
     */
    public function initialize(int $amountMinor, string $currency, string $email, string $reference, string $callbackUrl, array $metadata = []): array
    {
        $response = $this->request()->post('/transaction/initialize', [
            'amount' => $amountMinor,
            'currency' => $currency,
            'email' => $email,
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
        ]);

        return $this->data($response, 'Paystack rejected the transaction initialize request');
    }

    /**
     * Look up a transaction by reference. The caller is responsible for
     * checking `status === 'success'` and comparing amount/currency
     * before treating this as a confirmed payment.
     */
    public function verify(string $reference): array
    {
        $response = $this->request()->get('/transaction/verify/'.$reference);

        return $this->data($response, 'Paystack rejected the transaction verify request');
    }

    private function request(): PendingRequest
    {
        return Http::asJson()
            ->withHeader('Authorization', 'Bearer '.$this->secretKey())
            ->baseUrl(config('services.paystack.base_url'))
            ->timeout(10)
            ->retry(3, 500, throw: false);
    }

    private function secretKey(): string
    {
        return PlatformSetting::get('paystack_secret_key') ?: (string) config('services.paystack.secret_key');
    }

    private function data(Response $response, string $errorMessage): array
    {
        if ($response->failed()) {
            throw new RuntimeException($errorMessage.': '.$response->body());
        }

        return $response->json('data') ?? [];
    }
}
