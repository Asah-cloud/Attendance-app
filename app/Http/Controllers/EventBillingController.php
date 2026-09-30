<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventAttendeeCharge;
use App\Models\Feature;
use App\Services\EventBillingService;
use App\Services\PaystackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class EventBillingController extends Controller
{
    public function show(Event $event, EventBillingService $billing): View
    {
        $this->authorize('update', $event);
        $charge = $event->attendeeCharge;
        $estimate = $charge ? null : $billing->estimate($event);
        $features = $charge ? null : Feature::purchasable();
        $reviewFeatures = $charge?->isAwaitingReview() ? Feature::purchasable() : null;

        return view('events.billing.show', compact('event', 'charge', 'estimate', 'features', 'reviewFeatures'));
    }

    public function requestInvoice(Request $request, Event $event, EventBillingService $billing): RedirectResponse
    {
        $this->authorize('update', $event);
        $featureKeys = $this->validFeatureKeys($request);
        $billing->requestInvoice($event, $featureKeys);

        return redirect()->route('events.billing.show', $event)->with('success', 'Invoice requested — an admin will review it before it can be paid.');
    }

    public function approve(Request $request, Event $event, EventBillingService $billing): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($request->user()->hasRole('admin'), 403);

        $charge = $event->attendeeCharge;
        abort_unless($charge && $charge->isAwaitingReview(), 404);

        $validated = $request->validate([
            'discount' => ['nullable', 'numeric', 'min:0'],
            'discount_reason' => [
                Rule::requiredIf((float) ($request->input('discount') ?? 0) > 0),
                'nullable', 'string', 'max:255',
            ],
        ]);

        $discountMinor = (int) round(((float) ($validated['discount'] ?? 0)) * 100);
        $featureKeys = $this->validFeatureKeys($request);

        $billing->approveInvoice($charge, $request->user(), $featureKeys, $discountMinor, $validated['discount_reason'] ?? null);

        return redirect()->route('events.billing.show', $event)->with('success', 'Invoice approved and sent to the manager.');
    }

    public function downloadInvoice(Event $event, EventBillingService $billing): Response
    {
        $this->authorize('update', $event);
        $charge = $event->attendeeCharge;
        abort_unless($charge, 404);
        abort_if($charge->isAwaitingReview() && ! auth()->user()->hasRole('admin'), 404);

        return $billing->renderInvoicePdf($charge)->download(($charge->invoice_number ?: 'invoice-draft').'.pdf');
    }

    public function emailInvoice(Event $event, EventBillingService $billing): RedirectResponse
    {
        $this->authorize('update', $event);
        $charge = $event->attendeeCharge;
        abort_unless($charge, 404);

        $billing->resendInvoiceEmail($charge);

        return redirect()->route('events.billing.show', $event)->with('success', 'Invoice emailed.');
    }

    public function pay(Event $event, EventBillingService $billing): RedirectResponse
    {
        $this->authorize('update', $event);
        $charge = $event->attendeeCharge;
        abort_unless($charge, 404);

        try {
            $authorizationUrl = $billing->startCheckout($charge, route('events.billing.callback', $event));
        } catch (RuntimeException) {
            return redirect()->route('events.billing.show', $event)
                ->withErrors(['payment' => 'We could not start the payment with Paystack. Please try again.']);
        }

        return redirect()->away($authorizationUrl);
    }

    public function callback(Event $event, Request $request, PaystackService $paystack, EventBillingService $billing): RedirectResponse
    {
        $this->authorize('update', $event);
        $charge = $event->attendeeCharge;
        $reference = (string) $request->query('reference');
        abort_unless($charge && $charge->payment_reference === $reference, 404);

        if ($charge->status === EventAttendeeCharge::STATUS_PAID) {
            return redirect()->route('events.billing.show', $event)->with('success', 'Payment approved. This event\'s attendee bill is now paid.');
        }

        try {
            $data = $paystack->verify($reference);
        } catch (RuntimeException) {
            $data = ['status' => 'failed'];
        }

        $verified = ($data['status'] ?? null) === 'success'
            && (int) ($data['amount'] ?? 0) === $charge->amount_minor
            && ($data['currency'] ?? null) === $charge->currency;

        if (! $verified) {
            $charge->update(['status' => EventAttendeeCharge::STATUS_PAYMENT_FAILED]);

            return redirect()->route('events.billing.show', $event)
                ->withErrors(['payment' => 'Payment was not completed. Please try again.']);
        }

        $billing->confirmPayment($charge);

        return redirect()->route('events.billing.show', $event)->with('success', 'Payment approved. This event\'s attendee bill is now paid.');
    }

    private function validFeatureKeys(Request $request): array
    {
        return array_values(array_intersect(
            (array) $request->input('features', []),
            Feature::purchasable()->pluck('key')->all()
        ));
    }
}
