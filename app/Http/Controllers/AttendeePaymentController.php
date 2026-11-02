<?php

namespace App\Http\Controllers;

use App\Models\AttendeePayment;
use App\Models\EventRegistration;
use App\Models\FormResponse;
use App\Services\AttendeePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

/** Where Paystack sends people back after paying on a form or the registration form. */
class AttendeePaymentController extends Controller
{
    public function show(string $reference, AttendeePaymentService $payments): View|RedirectResponse
    {
        $payment = AttendeePayment::with(['payable', 'event'])->where('reference', $reference)->firstOrFail();

        if ($payments->verify($payment)) {
            return $this->done($payment)->with('payment_success', 'Payment received: '.AttendeePayment::money($payment->amount_minor, $payment->currency).'. Thank you!');
        }

        return view('payments.unfinished', ['payment' => $payment->fresh(['event.company'])]);
    }

    public function retry(string $reference, AttendeePaymentService $payments): RedirectResponse
    {
        $payment = AttendeePayment::with(['payable', 'company'])->where('reference', $reference)->firstOrFail();
        if ($payment->status === AttendeePayment::STATUS_PAID || $payment->payable?->payment_status === 'paid') {
            return $this->done($payment);
        }

        try {
            return redirect()->away($payments->retry($payment));
        } catch (RuntimeException) {
            return back()->with('error', 'We could not reach the payment page. Please try again in a moment.');
        }
    }

    private function done(AttendeePayment $payment): RedirectResponse
    {
        $payable = $payment->payable;

        return match (true) {
            $payable instanceof FormResponse => redirect()->route('forms.thank-you', [$payable->form->event->slug, $payable->form->slug]),
            $payable instanceof EventRegistration => redirect()->route('registrations.confirmation', $payable->management_token),
            default => abort(404),
        };
    }
}
