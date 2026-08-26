<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
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

        return view('superadmin.integrations.edit', ['paystack' => $paystack]);
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

    private function preview(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return str_repeat('•', 8).substr($value, -4);
    }
}
