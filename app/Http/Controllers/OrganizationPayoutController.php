<?php

namespace App\Http\Controllers;

use App\Services\PaystackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Where an organisation is paid for attendee payments: its Mobile Money wallet or bank account,
 * held at Paystack as a sub-account so payments settle straight to the organisation.
 */
class OrganizationPayoutController extends Controller
{
    public function edit(Request $request, PaystackService $paystack): View
    {
        $company = $request->user()->company;
        abort_unless($company, 403);

        return view('organization.payouts', [
            'company' => $company,
            'providers' => ['mobile_money' => $this->providers($paystack, 'mobile_money'), 'bank' => $this->providers($paystack, 'bank')],
        ]);
    }

    public function update(Request $request, PaystackService $paystack): RedirectResponse
    {
        $company = $request->user()->company;
        abort_unless($company, 403);

        $type = $request->input('payout_type') === 'bank' ? 'bank' : 'mobile_money';
        $providers = $this->providers($paystack, $type);
        $validated = $request->validate([
            'payout_business_name' => ['required', 'string', 'max:100'],
            'payout_type' => ['required', Rule::in(['mobile_money', 'bank'])],
            'payout_bank_code' => ['required', Rule::in(array_keys($providers))],
            'payout_account_number' => ['required', 'regex:/^\+?[0-9 ]{8,20}$/'],
        ], [
            'payout_bank_code.in' => 'Choose your Mobile Money network or bank from the list.',
            'payout_account_number.regex' => 'Enter the Mobile Money number or account number using digits only.',
        ]);
        $number = preg_replace('/\D+/', '', $validated['payout_account_number']);

        try {
            $subaccount = $paystack->saveSubaccount($company->payout_subaccount_code, $validated['payout_business_name'], $validated['payout_bank_code'], $number, $company->email);
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withInput()->withErrors(['payout_account_number' => 'Paystack couldn\'t accept these details. Check the number and network or bank, then try again.']);
        }

        $company->update([
            'payout_subaccount_code' => $subaccount['subaccount_code'] ?? $company->payout_subaccount_code,
            'payout_business_name' => $validated['payout_business_name'],
            'payout_type' => $validated['payout_type'],
            'payout_bank_code' => $validated['payout_bank_code'],
            'payout_bank_name' => $providers[$validated['payout_bank_code']],
            'payout_account_number' => $number,
            'payout_updated_at' => now(),
        ]);

        return back()->with('success', 'Payout account saved. Payments on your forms will be paid into it.');
    }

    /** @return array<string, string> */
    private function providers(PaystackService $paystack, string $type): array
    {
        try {
            return Cache::remember('paystack:payout-providers:'.$type, now()->addDay(), fn () => $paystack->payoutBanks($type));
        } catch (RuntimeException $exception) {
            report($exception);

            return [];
        }
    }
}
