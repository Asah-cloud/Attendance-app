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
    public function initialize(int $amountMinor, string $currency, string $email, string $reference, string $callbackUrl, array $metadata = [], array $extra = []): array
    {
        $response = $this->request()->post('/transaction/initialize', [
            'amount' => $amountMinor,
            'currency' => $currency,
            'email' => $email,
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
            ...$extra,
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

    /**
     * Ghana's Mobile Money providers or banks that can receive payouts, as [code => name].
     *
     * @return array<string, string>
     */
    public function payoutBanks(string $type): array
    {
        $response = $this->request()->get('/bank', ['currency' => 'GHS', 'type' => $type === 'bank' ? 'ghipss' : 'mobile_money', 'perPage' => 100]);

        return collect($this->list($response, 'Paystack could not list payout providers'))
            ->filter(fn ($bank) => ($bank['active'] ?? true) && filled($bank['code'] ?? null))
            ->mapWithKeys(fn ($bank) => [$bank['code'] => $bank['name']])
            ->all();
    }

    /**
     * Create or update the organisation's sub-account, where attendee payments are settled.
     * The platform's share is set per payment (transaction_charge), so the standing share is 0%.
     */
    public function saveSubaccount(?string $code, string $businessName, string $bankCode, string $accountNumber, ?string $email): array
    {
        $payload = array_filter([
            'business_name' => $businessName,
            'settlement_bank' => $bankCode,
            'account_number' => $accountNumber,
            'percentage_charge' => 0,
            'primary_contact_email' => $email,
        ], fn ($value) => $value !== null);

        $response = $code
            ? $this->request()->put('/subaccount/'.$code, $payload)
            : $this->request()->post('/subaccount', $payload);

        return $this->data($response, 'Paystack rejected the payout account');
    }

    private function list(Response $response, string $errorMessage): array
    {
        if ($response->failed()) {
            throw new RuntimeException($errorMessage.': '.$response->body());
        }

        return $response->json('data') ?? [];
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
