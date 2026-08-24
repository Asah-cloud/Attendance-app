<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class OrganizationBrandingController extends Controller
{
    public function edit(Request $request): View
    {
        return view('organization.branding', ['company' => $request->user()->company]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $request->user()->company;
        abort_unless($company, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $company->name = $validated['name'];

        if ($request->boolean('remove_logo') && $company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
            $company->logo_path = null;
        }

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $company->logo_path = $request->file('logo')->store('company-logos', 'public');
        }

        $company->save();

        return back()->with('success', 'Organization branding updated.');
    }

    public function updateMessaging(Request $request): RedirectResponse
    {
        $company = $request->user()->company;
        abort_unless($company, 403);

        $validated = $request->validate([
            'email_from_name' => ['nullable', 'string', 'max:255', 'required_with:email_from_address'],
            'email_from_address' => ['nullable', 'email:rfc', 'max:255'],
            'sms_sender_id' => ['nullable', 'string', 'min:3', 'max:11', 'regex:/^[A-Za-z0-9 ]+$/'],
        ]);

        $emailAddress = $validated['email_from_address'] ?? null;
        $smsSenderId = isset($validated['sms_sender_id']) ? trim($validated['sms_sender_id']) : null;

        if ($emailAddress !== $company->email_from_address) {
            $company->email_sender_status = $emailAddress ? 'pending' : 'unconfigured';
        }
        if ($smsSenderId !== $company->sms_sender_id) {
            $company->sms_sender_status = $smsSenderId ? 'pending' : 'unconfigured';
        }

        $company->email_from_name = $emailAddress ? ($validated['email_from_name'] ?? $company->name) : null;
        $company->email_from_address = $emailAddress;
        $company->sms_sender_id = $smsSenderId ?: null;
        $company->save();

        return back()->with('success', 'Messaging identities saved. New or changed identities must be approved before they are used.');
    }
}
