<?php

namespace App\Http\Controllers;

use App\Notifications\EmailDomainDnsInstructions;
use App\Services\EmailDomainLifecycleManager;
use App\Services\ResendDomainService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function updateMessaging(Request $request, ResendDomainService $resend): RedirectResponse
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

        $emailChanged = $emailAddress !== $company->email_from_address;
        if ($emailChanged) {
            $company->email_sender_status = $emailAddress ? 'pending' : 'unconfigured';
            $company->resend_domain_id = null;
            $company->resend_domain_name = null;
            $company->resend_domain_status = null;
            $company->resend_domain_records = null;
            $company->resend_setup_error = null;
            $company->resend_last_checked_at = null;
            $company->resend_setup_started_at = null;
            $company->resend_first_reminder_sent_at = null;
            $company->resend_delayed_notice_sent_at = null;
            $company->resend_failure_notice_sent_at = null;
            $company->resend_verified_notice_sent_at = null;
        }
        if ($smsSenderId !== $company->sms_sender_id) {
            $company->sms_sender_status = $smsSenderId ? 'pending' : 'unconfigured';
        }

        $company->email_from_name = $emailAddress ? ($validated['email_from_name'] ?? $company->name) : null;
        $company->email_from_address = $emailAddress;
        $company->sms_sender_id = $smsSenderId ?: null;
        $company->save();

        if ($emailAddress && ! $company->resend_domain_id) {
            $domainName = Str::afterLast($emailAddress, '@');

            try {
                $domain = $resend->create($domainName);
                $company->update([
                    'resend_domain_id' => $domain['id'],
                    'resend_domain_name' => $domain['name'],
                    'resend_domain_status' => $domain['status'],
                    'resend_domain_records' => $domain['records'],
                    'resend_setup_error' => null,
                    'resend_last_checked_at' => now(),
                    'resend_setup_started_at' => now(),
                ]);
                $request->user()->notify(new EmailDomainDnsInstructions($company->fresh()));
            } catch (\Throwable $exception) {
                report($exception);
                $company->update(['resend_setup_error' => $exception->getMessage()]);

                return back()->with('error', 'Your identity was saved, but Resend domain setup could not start. Please try again or contact support.');
            }
        }

        return back()->with('success', 'Messaging identities saved. New or changed identities must be approved before they are used.');
    }

    public function checkEmailDomain(Request $request, EmailDomainLifecycleManager $lifecycle): RedirectResponse
    {
        $company = $request->user()->company;
        abort_unless($company?->resend_domain_id, 404);

        try {
            $status = $lifecycle->checkCompany($company);
            $verified = $status === 'verified';

            return back()->with(
                $verified ? 'success' : 'error',
                $verified
                    ? 'Email domain verified. Your company sender address is now active.'
                    : 'Resend has not verified all DNS records yet. Check the records below and try again after DNS propagation.'
            );
        } catch (\Throwable $exception) {
            report($exception);
            $company->update(['resend_setup_error' => $exception->getMessage(), 'resend_last_checked_at' => now()]);

            return back()->with('error', 'We could not check the domain with Resend. Please try again later.');
        }
    }
}
