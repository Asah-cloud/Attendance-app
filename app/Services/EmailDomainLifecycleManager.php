<?php

namespace App\Services;

use App\Models\Company;
use App\Notifications\EmailDomainStatusNotification;

class EmailDomainLifecycleManager
{
    public function __construct(private ResendDomainService $resend) {}

    public function checkCompany(Company $company): string
    {
        $domain = $this->resend->checkVerification($company->resend_domain_id);
        $verified = $domain['status'] === 'verified';

        $company->update([
            'resend_domain_status' => $domain['status'],
            'resend_domain_records' => $domain['records'],
            'resend_setup_error' => null,
            'resend_last_checked_at' => now(),
            'email_sender_status' => $verified ? 'approved' : 'pending',
        ]);

        if ($verified && ! $company->resend_verified_notice_sent_at) {
            $this->notifyManagers($company, 'verified');
            $company->update(['resend_verified_notice_sent_at' => now()]);
        } elseif (in_array($domain['status'], ['failed', 'temporary_failure'], true) && ! $company->resend_failure_notice_sent_at) {
            $this->notifyManagers($company, 'failed');
            $company->update(['resend_failure_notice_sent_at' => now()]);
        }

        return $domain['status'];
    }

    public function processPending(): int
    {
        $processed = 0;

        Company::query()
            ->where('email_sender_status', 'pending')
            ->whereNotNull('resend_domain_id')
            ->with('users')
            ->chunkById(100, function ($companies) use (&$processed): void {
                foreach ($companies as $company) {
                    try {
                        $this->checkCompany($company);
                        $this->sendDueReminder($company->fresh('users'));
                        $processed++;
                    } catch (\Throwable $exception) {
                        report($exception);
                        $company->update([
                            'resend_setup_error' => $exception->getMessage(),
                            'resend_last_checked_at' => now(),
                        ]);
                    }
                }
            });

        return $processed;
    }

    private function sendDueReminder(Company $company): void
    {
        if ($company->email_sender_status !== 'pending'
            || ! $company->resend_setup_started_at
            || in_array($company->resend_domain_status, ['failed', 'temporary_failure'], true)) {
            return;
        }

        if ($company->resend_setup_started_at->lte(now()->subHours(72)) && ! $company->resend_delayed_notice_sent_at) {
            $this->notifyManagers($company, 'delayed');
            $company->update(['resend_delayed_notice_sent_at' => now()]);

            return;
        }

        if ($company->resend_setup_started_at->lte(now()->subHours(24)) && ! $company->resend_first_reminder_sent_at) {
            $this->notifyManagers($company, 'reminder');
            $company->update(['resend_first_reminder_sent_at' => now()]);
        }
    }

    private function notifyManagers(Company $company, string $type): void
    {
        foreach ($company->users->where('role', 'manager') as $manager) {
            $manager->notify(new EmailDomainStatusNotification($company, $type));
        }
    }
}
