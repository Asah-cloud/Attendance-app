<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class OnboardingController extends Controller
{
    public function pricing(): View
    {
        return view('pricing', ['plans' => config('plans.plans')]);
    }

    public function checkout(string $plan): View
    {
        return view('onboarding.checkout', ['planKey' => $plan, 'plan' => $this->plan($plan)]);
    }

    public function processTestPayment(Request $request, string $plan): RedirectResponse
    {
        $selectedPlan = $this->plan($plan);

        $request->session()->put('onboarding_payment', [
            'plan_key' => $plan,
            'price_minor' => $selectedPlan['price_minor'],
            'currency' => config('plans.currency'),
            'payment_reference' => 'TEST-'.Str::upper(Str::random(20)),
            'paid_at' => now()->toIso8601String(),
        ]);

        return redirect()->route('register')
            ->with('success', 'Test payment approved. Create your manager account to finish setup.');
    }

    public function createAccount(Request $request): View|RedirectResponse
    {
        $payment = $request->session()->get('onboarding_payment');

        if (! $this->validPaymentSession($payment)) {
            return redirect()->route('pricing')
                ->with('error', 'Select a plan and complete the test payment before creating an account.');
        }

        return view('auth.manager-register', [
            'plan' => $this->plan($payment['plan_key']),
            'payment' => $payment,
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $payment = $request->session()->get('onboarding_payment');

        if (! $this->validPaymentSession($payment)) {
            return redirect()->route('pricing')
                ->with('error', 'Your payment session is missing or invalid. Please select a plan again.');
        }

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $plan = $this->plan($payment['plan_key']);

        [$company, $user] = DB::transaction(function () use ($validated, $payment, $plan): array {
            $company = Company::create([
                'name' => $validated['company_name'],
                'email' => $validated['email'],
                'subscription_ends_at' => now()->addMonths((int) config('plans.billing_period_months'))->toDateString(),
                'subscription_started_at' => now(),
                'event_limit' => $plan['event_limit'],
                'is_active' => true,
                'plan_key' => $payment['plan_key'],
                'plan_price_minor' => $payment['price_minor'],
                'billing_currency' => $payment['currency'],
                'payment_reference' => $payment['payment_reference'],
                'subscription_auto_renews' => true,
            ]);

            SubscriptionPayment::create([
                'company_id' => $company->id,
                'plan_key' => $payment['plan_key'],
                'type' => 'initial',
                'amount_minor' => $payment['price_minor'],
                'currency' => $payment['currency'],
                'payment_reference' => $payment['payment_reference'],
                'status' => 'paid',
                'paid_at' => $payment['paid_at'],
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'email_verified_at' => now(),
                'password' => Hash::make($validated['password']),
                'category' => 'manager',
                'role' => 'manager',
                'company_id' => $company->id,
            ]);

            $user->assignRole(Role::findOrCreate('manager'));

            return [$company, $user];
        });

        event(new Registered($user));
        Auth::login($user);
        $request->session()->forget('onboarding_payment');
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', "Welcome to {$company->name}. Your manager workspace is ready.");
    }

    private function plan(string $plan): array
    {
        $selectedPlan = config("plans.plans.{$plan}");
        abort_unless(is_array($selectedPlan), 404);

        return $selectedPlan;
    }

    private function validPaymentSession(mixed $payment): bool
    {
        if (! is_array($payment) || ! isset($payment['plan_key'], $payment['price_minor'], $payment['currency'], $payment['payment_reference'], $payment['paid_at'])) {
            return false;
        }

        $plan = config("plans.plans.{$payment['plan_key']}");

        try {
            $paidAt = Carbon::parse($payment['paid_at']);
        } catch (\Throwable) {
            return false;
        }

        return is_array($plan)
            && $payment['price_minor'] === $plan['price_minor']
            && $payment['currency'] === config('plans.currency')
            && str_starts_with($payment['payment_reference'], 'TEST-')
            && $paidAt->isAfter(now()->subMinutes(30));
    }
}
