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
        $reviewFeatures = $charge?->isEditable() ? Feature::purchasable() : null;
        $companyManagers = $charge?->hasApprovedInvoice() ? $event->company->users()->where('role', 'manager')->get() : null;

        return view('events.billing.show', compact('event', 'charge', 'estimate', 'features', 'reviewFeatures', 'companyManagers'));
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
        abort_unless($charge && $charge->isEditable(), 404);
        $wasAlreadyInvoiced = $charge->hasApprovedInvoice();

        $validated = $request->validate([
            'discount' => ['nullable', 'numeric', 'min:0'],
            'discount_reason' => [
                Rule::requiredIf((float) ($request->input('discount') ?? 0) > 0),
                'nullable', 'string', 'max:255',
            ],
            'feature_amounts' => ['nullable', 'array'],
            'feature_amounts.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $discountMinor = (int) round(((float) ($validated['discount'] ?? 0)) * 100);
        $featureKeys = $this->validFeatureKeys($request);
        $featureAmounts = [];
        foreach ($validated['feature_amounts'] ?? [] as $key => $amount) {
            if (in_array($key, $featureKeys, true) && $amount !== null && $amount !== '') {
                $featureAmounts[$key] = (int) round(((float) $amount) * 100);
            }
        }

        $billing->approveInvoice($charge, $request->user(), $featureKeys, $featureAmounts, $discountMinor, $validated['discount_reason'] ?? null);

        $message = $wasAlreadyInvoiced
            ? 'Invoice updated. Re-send it if managers already have a copy.'
            : 'Invoice saved. Preview, download, or send it to managers below.';

        return redirect()->route('events.billing.show', $event)->with('success', $message);
    }

    public function downloadInvoice(Request $request, Event $event, EventBillingService $billing): Response
    {
        $this->authorize('update', $event);
        $charge = $event->attendeeCharge;
        abort_unless($charge, 404);
        abort_if(! $charge->hasApprovedInvoice() && ! auth()->user()->hasRole('admin'), 404);

        $pdf = $billing->renderInvoicePdf($charge);
        $filename = ($charge->invoice_number ?: 'invoice-draft').'.pdf';

        return $request->boolean('preview') ? $pdf->stream($filename) : $pdf->download($filename);
    }

    public function emailInvoice(Request $request, Event $event, EventBillingService $billing): RedirectResponse
    {
        $this->authorize('update', $event);
        $charge = $event->attendeeCharge;
        abort_unless($charge, 404);

        $billing->sendInvoiceEmail($charge, [$request->user()]);

        return redirect()->route('events.billing.show', $event)->with('success', 'Invoice emailed to you.');
    }

    public function sendInvoice(Request $request, Event $event, EventBillingService $billing): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($request->user()->hasRole('admin'), 403);

        $charge = $event->attendeeCharge;
        abort_unless($charge && $charge->hasApprovedInvoice(), 404);

        $validated = $request->validate([
            'managers' => ['required', 'array', 'min:1'],
            'managers.*' => ['integer'],
        ]);

        $managers = $event->company->users()
            ->where('role', 'manager')
            ->whereIn('id', $validated['managers'])
            ->get();

        $billing->sendInvoiceEmail($charge, $managers);

        return redirect()->route('events.billing.show', $event)->with('success', 'Invoice sent to '.$managers->count().' manager(s).');
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

    public function markPaid(Request $request, Event $event, EventBillingService $billing): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($request->user()->hasRole('admin'), 403);

        $charge = $event->attendeeCharge;
        abort_unless($charge && $charge->canMarkPaidManually(), 404);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $billing->markPaidManually($charge, $request->user(), $validated['note'] ?? null);

        return redirect()->route('events.billing.show', $event)->with('success', 'Marked as paid.');
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
