<?php

namespace App\Http\Controllers;

use App\Models\AttendeePricingTier;
use App\Models\Company;
use App\Models\Feature;
use App\Models\User;
use App\Notifications\CompanyWelcomeNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class OnboardingController extends Controller
{
    public function pricing(): View
    {
        $payPerEventTiers = AttendeePricingTier::query()
            ->where('scope_type', AttendeePricingTier::SCOPE_PLATFORM)
            ->orderBy('band_from')
            ->get();

        return view('pricing', [
            'payPerEventTiers' => $payPerEventTiers,
            'features' => Feature::purchasable(),
        ]);
    }

    public function choosePayPerEvent(Request $request): RedirectResponse
    {
        $request->session()->put('onboarding_billing_mode', Company::BILLING_MODE_PAY_PER_EVENT);

        return redirect()->route('register')
            ->with('success', 'Create your manager account to finish setup.');
    }

    public function createAccount(Request $request): View|RedirectResponse
    {
        if (! $this->hasChosenPayPerEvent($request)) {
            return redirect()->route('pricing')
                ->with('error', 'Start from the pricing page to create your company account.');
        }

        return view('auth.manager-register');
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        if (! $this->hasChosenPayPerEvent($request)) {
            return redirect()->route('pricing')
                ->with('error', 'Start from the pricing page to create your company account.');
        }

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $logoPath = $request->hasFile('logo') ? $request->file('logo')->store('company-logos', 'public') : null;

        try {
            [$company, $user] = DB::transaction(function () use ($validated, $logoPath): array {
                $company = Company::create([
                    'name' => $validated['company_name'],
                    'email' => $validated['email'],
                    'logo_path' => $logoPath,
                    'billing_mode' => Company::BILLING_MODE_PAY_PER_EVENT,
                    'event_limit' => 5,
                    'is_active' => true,
                    'billing_currency' => config('plans.currency'),
                    'subscription_auto_renews' => false,
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
        } catch (\Throwable $exception) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }

            throw $exception;
        }

        event(new Registered($user));
        $user->notify(new CompanyWelcomeNotification($company));
        Auth::login($user);
        $request->session()->forget('onboarding_billing_mode');
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', "Welcome to {$company->name}. Your manager workspace is ready.");
    }

    private function hasChosenPayPerEvent(Request $request): bool
    {
        return $request->session()->get('onboarding_billing_mode') === Company::BILLING_MODE_PAY_PER_EVENT;
    }
}
