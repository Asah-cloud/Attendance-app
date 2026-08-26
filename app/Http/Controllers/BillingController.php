<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Services\PaystackService;
use App\Services\SubscriptionBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $company = $this->company($request);
        $payments = $company->subscriptionPayments()->latest('paid_at')->paginate(10);
        $attendeeCharges = $company->isPayPerEvent()
            ? $company->attendeeCharges()->with('event')->latest('finalized_at')->paginate(10, ['*'], 'charges')
            : null;

        return view('billing.index', [
            'company' => $company,
            'payments' => $payments,
            'attendeeCharges' => $attendeeCharges,
            'plans' => Plan::allKeyed(),
        ]);
    }

    public function checkout(Request $request, string $plan): View
    {
        $company = $this->company($request);
        abort_unless($company->is_active, 403, 'This company has been suspended. Contact support.');

        return view('billing.checkout', [
            'company' => $company,
            'planKey' => $plan,
            'plan' => $this->plan($plan),
        ]);
    }

    public function startCheckout(Request $request, string $plan, PaystackService $paystack): RedirectResponse
    {
        $company = $this->company($request);
        abort_unless($company->is_active, 403, 'This company has been suspended. Contact support.');
        $selectedPlan = $this->plan($plan);
        $reference = 'SUB-'.$company->id.'-'.Str::upper(Str::random(16));

        $payment = SubscriptionPayment::create([
            'company_id' => $company->id,
            'plan_key' => $plan,
            'type' => $company->plan_key === $plan ? 'renewal' : 'plan_change',
            'amount_minor' => $selectedPlan['price_minor'],
            'currency' => config('plans.currency'),
            'payment_reference' => $reference,
            'status' => SubscriptionPayment::STATUS_PENDING,
        ]);

        try {
            $data = $paystack->initialize(
                amountMinor: $selectedPlan['price_minor'],
                currency: config('plans.currency'),
                email: $company->email ?: $request->user()->email,
                reference: $reference,
                callbackUrl: route('billing.checkout.callback'),
                metadata: ['flow' => 'subscription', 'subscription_payment_id' => $payment->id],
            );
        } catch (RuntimeException) {
            $payment->update(['status' => SubscriptionPayment::STATUS_FAILED]);

            return redirect()->route('billing.checkout', $plan)
                ->withErrors(['payment' => 'We could not start the payment with Paystack. Please try again.']);
        }

        return redirect()->away($data['authorization_url']);
    }

    public function checkoutCallback(Request $request, PaystackService $paystack, SubscriptionBillingService $subscriptions): RedirectResponse
    {
        $company = $this->company($request);
        $reference = (string) $request->query('reference');
        $payment = SubscriptionPayment::where('payment_reference', $reference)
            ->where('company_id', $company->id)
            ->firstOrFail();

        if ($payment->status === SubscriptionPayment::STATUS_PAID) {
            return redirect()->route('billing.index')->with('success', 'Payment approved and subscription updated.');
        }

        try {
            $data = $paystack->verify($reference);
        } catch (RuntimeException) {
            $data = ['status' => 'failed'];
        }

        $verified = ($data['status'] ?? null) === 'success'
            && (int) ($data['amount'] ?? 0) === $payment->amount_minor
            && ($data['currency'] ?? null) === $payment->currency;

        if (! $verified) {
            $payment->update(['status' => SubscriptionPayment::STATUS_FAILED]);

            return redirect()->route('billing.checkout', $payment->plan_key)
                ->withErrors(['payment' => 'Payment was not completed. Please try again.']);
        }

        $subscriptions->confirmPayment($payment);

        return redirect()->route('billing.index')->with('success', 'Payment approved and subscription updated.');
    }

    public function updateContact(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $this->company($request)->update(['email' => $validated['email']]);

        return back()->with('success', 'Billing contact updated.');
    }

    public function cancelRenewal(Request $request): RedirectResponse
    {
        $this->company($request)->update([
            'subscription_auto_renews' => false,
            'subscription_cancelled_at' => now(),
        ]);

        return back()->with('success', 'Automatic renewal cancelled. Access remains available until the subscription end date.');
    }

    public function resumeRenewal(Request $request): RedirectResponse
    {
        $this->company($request)->update([
            'subscription_auto_renews' => true,
            'subscription_cancelled_at' => null,
        ]);

        return back()->with('success', 'Automatic renewal resumed.');
    }

    private function company(Request $request): Company
    {
        return Company::findOrFail($request->user()->company_id);
    }

    private function plan(string $plan): array
    {
        return Plan::arrayByKey($plan);
    }
}
