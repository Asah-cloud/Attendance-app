<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\SubscriptionPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $company = $this->company($request);
        $payments = $company->subscriptionPayments()->latest('paid_at')->paginate(10);

        return view('billing.index', [
            'company' => $company,
            'payments' => $payments,
            'plans' => config('plans.plans'),
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

    public function processTestPayment(Request $request, string $plan): RedirectResponse
    {
        $company = $this->company($request);
        abort_unless($company->is_active, 403, 'This company has been suspended. Contact support.');
        $selectedPlan = $this->plan($plan);

        DB::transaction(function () use ($company, $plan, $selectedPlan): void {
            $company = Company::query()->lockForUpdate()->findOrFail($company->id);
            $previousPlan = $company->plan_key;
            $reference = 'TEST-'.Str::upper(Str::random(20));
            $today = now()->startOfDay();
            $currentEnd = $company->subscription_ends_at?->startOfDay();
            $extensionBase = $currentEnd && $currentEnd->gte($today) ? $currentEnd : $today;

            $company->update([
                'plan_key' => $plan,
                'plan_price_minor' => $selectedPlan['price_minor'],
                'billing_currency' => config('plans.currency'),
                'payment_reference' => $reference,
                'subscription_started_at' => $company->subscription_started_at ?? now(),
                'subscription_ends_at' => $extensionBase->copy()->addMonths((int) config('plans.billing_period_months')),
                'subscription_auto_renews' => true,
                'subscription_cancelled_at' => null,
                'event_limit' => $selectedPlan['event_limit'],
            ]);

            SubscriptionPayment::create([
                'company_id' => $company->id,
                'plan_key' => $plan,
                'type' => $previousPlan === $plan ? 'renewal' : 'plan_change',
                'amount_minor' => $selectedPlan['price_minor'],
                'currency' => config('plans.currency'),
                'payment_reference' => $reference,
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        });

        return redirect()->route('billing.index')->with('success', 'Test payment approved and subscription updated.');
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
        $selectedPlan = config("plans.plans.{$plan}");
        abort_unless(is_array($selectedPlan), 404);

        return $selectedPlan;
    }
}
