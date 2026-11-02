<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Event;
use App\Models\Form;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** The "Collect payment" card on a custom form and on the registration form. */
trait SavesPaymentSettings
{
    private function savePaymentSettings(Request $request, Event|Form $payable): RedirectResponse
    {
        $validated = $request->validate($payable::paymentSettingRules());
        $enabled = (bool) ($validated['payment_enabled'] ?? false);
        $hasOptions = (bool) $payable::parsePaymentOptions($validated['payment_options'] ?? null);

        if ($enabled && ! $hasOptions && ! filled($validated['payment_amount'] ?? null)) {
            throw ValidationException::withMessages(['payment_amount' => 'Enter the amount people pay, or add choices with their prices.']);
        }

        if ($enabled && ! filled($payable->paymentCompany()?->payout_subaccount_code)) {
            throw ValidationException::withMessages(['payment_enabled' => 'Add your payout account under Organization → Payouts before collecting payments.']);
        }
        if ($enabled && ($validated['payment_payer_sets_amount'] ?? false) && filled($validated['payment_minimum'] ?? null)
            && filled($validated['payment_amount'] ?? null) && $validated['payment_minimum'] > $validated['payment_amount']) {
            throw ValidationException::withMessages(['payment_minimum' => 'The minimum can\'t be more than the amount.']);
        }

        $payable->update($payable->paymentColumns($validated));

        return back()->with('success', $enabled ? 'Payment switched on.' : 'Payment settings saved. People are not asked to pay.');
    }
}
