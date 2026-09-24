<?php

namespace App\Services;

use Resend\Laravel\Facades\Resend;
use RuntimeException;

class ResendDomainService
{
    public function create(string $domainName): array
    {
        if (! config('services.resend.key')) {
            throw new RuntimeException('Resend is not configured. Add RESEND_API_KEY before starting domain setup.');
        }

        try {
            $domain = Resend::domains()->create([
                'name' => $domainName,
                'capabilities' => [
                    'sending' => 'enabled',
                    'receiving' => 'disabled',
                ],
            ]);
        } catch (\Throwable $exception) {
            // Resend rejects a domain that already exists in our account (e.g. from an
            // earlier attempt that was never saved locally). Adopt it instead of failing,
            // otherwise the company is left with no domain ID and can never be verified.
            $existing = $this->findExisting($domainName);
            if (! $existing) {
                throw $exception;
            }

            $domain = Resend::domains()->get($existing->id);
        }

        return $this->domainData($domain);
    }

    private function findExisting(string $domainName): ?object
    {
        try {
            foreach ((Resend::domains()->list()->data ?? []) as $domain) {
                $domain = (object) $domain;
                if (strcasecmp($domain->name, $domainName) === 0) {
                    return $domain;
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    public function checkVerification(string $domainId): array
    {
        if (! config('services.resend.key')) {
            throw new RuntimeException('Resend is not configured. Add RESEND_API_KEY before checking verification.');
        }

        $domain = Resend::domains()->get($domainId);

        // Resend keeps re-checking a pending domain by itself. Asking it to restart
        // verification resets records that were part-way through, so only do that once
        // a check has actually failed (or never started).
        if (in_array($domain->status, ['not_started', 'failed', 'partially_failed', 'temporary_failure'], true)) {
            Resend::domains()->verify($domainId);
            $domain = Resend::domains()->get($domainId);
        }

        return $this->domainData($domain);
    }

    private function domainData(object $domain): array
    {
        return [
            'id' => $domain->id,
            'name' => $domain->name,
            'status' => $domain->status,
            'records' => $domain->records ?? [],
        ];
    }
}
