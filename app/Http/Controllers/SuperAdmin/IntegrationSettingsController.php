<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AttendeePayment;
use App\Models\PlatformSetting;
use App\Services\AttendeePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IntegrationSettingsController extends Controller
{
    private const KEYS = [
        'paystack_secret_key' => 'Paystack secret key',
        'paystack_public_key' => 'Paystack public key',
    ];

    public function edit(): View
    {
        $paystack = [
            'secret_key_preview' => $this->preview(PlatformSetting::get('paystack_secret_key')),
            'public_key_preview' => $this->preview(PlatformSetting::get('paystack_public_key')),
        ];

        return view('superadmin.integrations.edit', [
            'paystack' => $paystack,
            'feeEnabled' => AttendeePaymentService::feeEnabled(),
            'feePercent' => AttendeePaymentService::feePercent(),
            'attendeePayments' => AttendeePayment::where('status', AttendeePayment::STATUS_PAID)->selectRaw('count(*) as count, coalesce(sum(amount_minor), 0) as amount, coalesce(sum(platform_fee_minor), 0) as fees')->first(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'paystack_secret_key' => ['nullable', 'string', 'max:255'],
            'paystack_public_key' => ['nullable', 'string', 'max:255'],
        ]);

        foreach (self::KEYS as $key => $label) {
            if (filled($validated[$key] ?? null)) {
                PlatformSetting::set($key, $validated[$key]);
            }
        }

        return back()->with('success', 'Integration settings updated.');
    }

    /** The platform's share of attendee payments on forms: on or off, and how much. */
    public function updateAttendeePaymentFee(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'fee_enabled' => ['required', 'boolean'],
            'fee_percent' => ['nullable', 'numeric', 'min:0', 'max:20', 'required_if_accepted:fee_enabled'],
        ]);

        PlatformSetting::set(AttendeePaymentService::FEE_ENABLED, $validated['fee_enabled'] ? '1' : '0');
        if (filled($validated['fee_percent'] ?? null)) {
            PlatformSetting::set(AttendeePaymentService::FEE_PERCENT, (string) (float) $validated['fee_percent']);
        }

        return back()->with('success', $validated['fee_enabled'] ? 'Platform fee switched on.' : 'Platform fee switched off.');
    }

    private function preview(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return str_repeat('•', 8).substr($value, -4);
    }
}
