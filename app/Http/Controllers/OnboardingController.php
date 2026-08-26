<?php

namespace App\Http\Controllers;

use App\Models\AttendeePricingTier;
use App\Models\Company;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\CompanyWelcomeNotification;
use App\Services\PaystackService;
use Carbon\Carbon;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Spatie\Permission\Models\Role;

class OnboardingController extends Controller
{
    public function pricing(): View
    {
        $payPerEventTiers = AttendeePricingTier::query()
            ->where('scope_type', AttendeePricingTier::SCOPE_PLATFORM)
            ->orderBy('band_from')
            ->get();

        return view('pricing', ['plans' => Plan::allKeyed(), 'payPerEventTiers' => $payPerEventTiers]);
    }

    public function choosePayPerEvent(Request $request): RedirectResponse
    {
        $request->session()->forget('onboarding_payment');
        $request->session()->put('onboarding_billing_mode', Company::BILLING_MODE_PAY_PER_EVENT);

        return redirect()->route('register')
            ->with('success', 'Pay-per-event selected. Create your manager account to finish setup.');
    }

    public function checkout(string $plan): View
    {
        return view('onboarding.checkout', ['planKey' => $plan, 'plan' => $this->plan($plan)]);
    }

    public function startCheckout(Request $request, string $plan, PaystackService $paystack): RedirectResponse
    {
        $selectedPlan = $this->plan($plan);
        $validated = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
        ]);
        $reference = 'ONB-'.Str::upper(Str::random(20));

        $request->session()->forget('onboarding_billing_mode');
        $request->session()->put('onboarding_checkout_pending', [
            'plan_key' => $plan,
            'reference' => $reference,
            'email' => $validated['email'],
            'started_at' => now()->toIso8601String(),
        ]);

        try {
            $data = $paystack->initialize(
                amountMinor: $selectedPlan['price_minor'],
                currency: config('plans.currency'),
                email: $validated['email'],
                reference: $reference,
                callbackUrl: route('checkout.callback'),
                metadata: ['flow' => 'onboarding', 'plan_key' => $plan],
            );
        } catch (RuntimeException) {
            return redirect()->route('checkout', $plan)
                ->withErrors(['payment' => 'We could not start the payment with Paystack. Please try again.']);
        }

        return redirect()->away($data['authorization_url']);
    }

    public function checkoutCallback(Request $request, PaystackService $paystack): RedirectResponse
    {
        $pending = $request->session()->get('onboarding_checkout_pending');
        $reference = (string) $request->query('reference');

        if (! is_array($pending) || ($pending['reference'] ?? null) !== $reference) {
            return redirect()->route('pricing')->with('error', 'Your checkout session could not be found. Please try again.');
        }

        $selectedPlan = $this->plan($pending['plan_key']);

        try {
            $data = $paystack->verify($reference);
        } catch (RuntimeException) {
            $data = ['status' => 'failed'];
        }

        $verified = ($data['status'] ?? null) === 'success'
            && (int) ($data['amount'] ?? 0) === $selectedPlan['price_minor']
            && ($data['currency'] ?? null) === config('plans.currency');

        if (! $verified) {
            $request->session()->forget('onboarding_checkout_pending');

            return redirect()->route('checkout', $pending['plan_key'])
                ->withErrors(['payment' => 'Payment was not completed. Please try again.']);
        }

        $request->session()->forget('onboarding_checkout_pending');
        $request->session()->put('onboarding_payment', [
            'plan_key' => $pending['plan_key'],
            'price_minor' => $selectedPlan['price_minor'],
            'currency' => config('plans.currency'),
            'payment_reference' => $reference,
            'email' => $pending['email'],
            'paid_at' => now()->toIso8601String(),
        ]);

        return redirect()->route('register')
            ->with('success', 'Payment approved. Create your manager account to finish setup.');
    }

    public function createAccount(Request $request): View|RedirectResponse
    {
        $payment = $request->session()->get('onboarding_payment');
        $payPerEvent = $request->session()->get('onboarding_billing_mode') === Company::BILLING_MODE_PAY_PER_EVENT;

        if (! $payPerEvent && ! $this->validPaymentSession($payment)) {
            return redirect()->route('pricing')
                ->with('error', 'Select a plan and complete the test payment before creating an account.');
        }

        return view('auth.manager-register', [
            'plan' => $payPerEvent ? null : $this->plan($payment['plan_key']),
            'payment' => $payPerEvent ? null : $payment,
            'payPerEvent' => $payPerEvent,
        ]);
    }

    public function storeAccount(Request $request, PaystackService $paystack): RedirectResponse
    {
        $payment = $request->session()->get('onboarding_payment');
        $payPerEvent = $request->session()->get('onboarding_billing_mode') === Company::BILLING_MODE_PAY_PER_EVENT;

        if (! $payPerEvent && (! $this->validPaymentSession($payment) || ! $this->paymentConfirmedByPaystack($payment, $paystack))) {
            return redirect()->route('pricing')
                ->with('error', 'Your payment session is missing or invalid. Please select a plan again.');
        }

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        if (! $payPerEvent && $validated['email'] !== $payment['email']) {
            throw ValidationException::withMessages(['email' => 'This must match the email you paid with.']);
        }

        $plan = $payPerEvent ? null : $this->plan($payment['plan_key']);
        $logoPath = $request->hasFile('logo') ? $request->file('logo')->store('company-logos', 'public') : null;

        try {
            [$company, $user] = DB::transaction(function () use ($validated, $payment, $plan, $payPerEvent, $logoPath): array {
                $company = Company::create([
                    'name' => $validated['company_name'],
                    'email' => $validated['email'],
                    'logo_path' => $logoPath,
                    'billing_mode' => $payPerEvent ? Company::BILLING_MODE_PAY_PER_EVENT : Company::BILLING_MODE_SUBSCRIPTION,
                    'subscription_ends_at' => $payPerEvent ? null : now()->addMonths((int) config('plans.billing_period_months'))->toDateString(),
                    'subscription_started_at' => $payPerEvent ? null : now(),
                    'event_limit' => $payPerEvent ? 5 : $plan['event_limit'],
                    'is_active' => true,
                    'plan_key' => $payPerEvent ? null : $payment['plan_key'],
                    'plan_price_minor' => $payPerEvent ? null : $payment['price_minor'],
                    'billing_currency' => $payPerEvent ? config('plans.currency') : $payment['currency'],
                    'payment_reference' => $payPerEvent ? null : $payment['payment_reference'],
                    'subscription_auto_renews' => ! $payPerEvent,
                ]);

                if (! $payPerEvent) {
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
                }

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
        } catch (\Throwable $exception) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }

            throw $exception;
        }

        event(new Registered($user));
        $user->notify(new CompanyWelcomeNotification($company));
        Auth::login($user);
        $request->session()->forget(['onboarding_payment', 'onboarding_billing_mode']);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', "Welcome to {$company->name}. Your manager workspace is ready.");
    }

    private function plan(string $plan): array
    {
        return Plan::arrayByKey($plan);
    }

    private function validPaymentSession(mixed $payment): bool
    {
        if (! is_array($payment) || ! isset($payment['plan_key'], $payment['price_minor'], $payment['currency'], $payment['payment_reference'], $payment['paid_at'], $payment['email'])) {
            return false;
        }

        $plan = Plan::where('key', $payment['plan_key'])->first();

        try {
            $paidAt = Carbon::parse($payment['paid_at']);
        } catch (\Throwable) {
            return false;
        }

        return $plan !== null
            && $payment['price_minor'] === $plan->price_minor
            && $payment['currency'] === config('plans.currency')
            && str_starts_with($payment['payment_reference'], 'ONB-')
            && $paidAt->isAfter(now()->subMinutes(30));
    }

    /**
     * Defense-in-depth for the onboarding flow specifically: unlike the
     * subscription/event-billing flows, there's no DB row backing this
     * payment until the Company is created below, so the session payload
     * is the only record — re-confirm with Paystack before trusting it.
     */
    private function paymentConfirmedByPaystack(array $payment, PaystackService $paystack): bool
    {
        try {
            $data = $paystack->verify($payment['payment_reference']);
        } catch (RuntimeException) {
            return false;
        }

        return ($data['status'] ?? null) === 'success'
            && (int) ($data['amount'] ?? 0) === $payment['price_minor']
            && ($data['currency'] ?? null) === $payment['currency'];
    }
}
