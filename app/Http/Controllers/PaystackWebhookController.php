<?php

namespace App\Http\Controllers;

use App\Models\EventAttendeeCharge;
use App\Services\EventBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function handle(Request $request, EventBillingService $eventBilling): JsonResponse
    {
        $payload = (array) $request->json()->all();
        $reference = $payload['data']['reference'] ?? null;

        if (($payload['event'] ?? null) !== 'charge.success' || ! is_string($reference)) {
            return response()->json(['status' => 'ignored']);
        }

        match (true) {
            str_starts_with($reference, 'EVB-') => $this->confirmEventBilling($reference, $eventBilling),
            str_starts_with($reference, 'ONB-') => Log::warning('Paystack: onboarding payment succeeded with no matching account (possible abandoned signup)', ['reference' => $reference]),
            default => Log::warning('Paystack webhook: unrecognized reference prefix', ['reference' => $reference]),
        };

        return response()->json(['status' => 'ok']);
    }

    private function confirmEventBilling(string $reference, EventBillingService $eventBilling): void
    {
        $charge = EventAttendeeCharge::where('payment_reference', $reference)->first();

        if (! $charge) {
            Log::warning('Paystack webhook: no matching EventAttendeeCharge for reference', ['reference' => $reference]);

            return;
        }

        $eventBilling->confirmPayment($charge);
    }
}
