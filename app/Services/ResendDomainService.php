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

        $domain = Resend::domains()->create([
            'name' => $domainName,
            'capabilities' => [
                'sending' => 'enabled',
                'receiving' => 'disabled',
            ],
        ]);

        return $this->domainData($domain);
    }

    public function checkVerification(string $domainId): array
    {
        if (! config('services.resend.key')) {
            throw new RuntimeException('Resend is not configured. Add RESEND_API_KEY before checking verification.');
        }

        $domain = Resend::domains()->get($domainId);

        if ($domain->status !== 'verified') {
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
