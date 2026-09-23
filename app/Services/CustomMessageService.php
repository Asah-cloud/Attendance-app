<?php

namespace App\Services;

class CustomMessageService
{
    public const MODE_SMART = 'smart';

    public const MODE_EMAIL_ONLY = 'email_only';

    public const MODE_SMS_ONLY = 'sms_only';

    public const MODES = [self::MODE_SMART, self::MODE_EMAIL_ONLY, self::MODE_SMS_ONLY];

    /**
     * Which single channel (if any) a recipient should get for the given
     * send mode. A recipient never gets both — Ghana numbers get SMS,
     * everyone else gets email, and "email only"/"sms only" force that
     * choice for the whole campaign regardless of locality.
     */
    public function determineChannel(string $mode, ?string $email, ?string $phone): ?string
    {
        $hasEmail = filled($email) && ! str_ends_with($email, '@example.invalid');
        $isGhana = config('services.arkesel.enabled') && PhoneNumberService::isGhanaNumber($phone);

        return match ($mode) {
            self::MODE_EMAIL_ONLY => $hasEmail ? 'mail' : null,
            self::MODE_SMS_ONLY => $isGhana ? 'sms' : null,
            default => $isGhana ? 'sms' : ($hasEmail ? 'mail' : null),
        };
    }

    /** @return array{mail: int, sms: int, skipped: int} */
    public function previewCounts(string $mode, iterable $recipients): array
    {
        $counts = ['mail' => 0, 'sms' => 0, 'skipped' => 0];

        foreach ($recipients as $recipient) {
            $channel = $this->determineChannel($mode, $recipient['email'] ?? null, $recipient['phone'] ?? null);
            $counts[$channel === 'sms' ? 'sms' : ($channel === 'mail' ? 'mail' : 'skipped')]++;
        }

        return $counts;
    }
}
