<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventAttendeeCharge;
use App\Services\EventBillingService;
use App\Services\PaystackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class EventBillingController extends Controller
{
    public function show(Event $event, EventBillingService $billing): View
    {
        $this->authorize('update', $event);
        $charge = $event->attendeeCharge;
        $estimate = $charge ? null : $billing->estimate($event);

        return view('events.billing.show', compact('event', 'charge', 'estimate'));
    }

    public function finalize(Event $event, EventBillingService $billing): RedirectResponse
    {
        $this->authorize('update', $event);
        $billing->finalize($event);

        return redirect()->route('events.billing.show', $event)->with('success', 'Attendee bill finalized. Review it below and complete payment.');
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
}
