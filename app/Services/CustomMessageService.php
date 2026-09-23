<?php

namespace App\Services;

class CustomMessageService
{
    public const MODE_SMART = 'smart';

    public const MODE_EMAIL_ONLY = 'email_only';

    public const MODE_SMS_ONLY = 'sms_only';

    public const MODE_BOTH = 'both';

    public const MODES = [self::MODE_SMART, self::MODE_EMAIL_ONLY, self::MODE_SMS_ONLY, self::MODE_BOTH];

    /**
     * Which channel(s) a recipient should get for the given send mode. In
     * every mode but "both", a recipient gets at most one channel — Ghana
     * numbers get SMS, everyone else gets email. "Both" sends independently
     * to every channel the recipient has, with no locality routing at all.
     * A channel is only ever included if its message body was actually
     * written — an empty SMS box means nobody gets texted, regardless of
     * their phone number.
     *
     * @return list<string> zero, one, or two of 'mail'/'sms'
     */
    public function determineChannels(string $mode, ?string $email, ?string $phone, bool $hasEmailBody, bool $hasSmsBody): array
    {
        $hasEmail = $hasEmailBody && filled($email) && ! str_ends_with($email, '@example.invalid');
        $isGhana = $hasSmsBody && config('services.arkesel.enabled') && PhoneNumberService::isGhanaNumber($phone);

        return match ($mode) {
            self::MODE_EMAIL_ONLY => $hasEmail ? ['mail'] : [],
            self::MODE_SMS_ONLY => $isGhana ? ['sms'] : [],
            self::MODE_BOTH => array_values(array_filter([$hasEmail ? 'mail' : null, $isGhana ? 'sms' : null])),
            default => $isGhana ? ['sms'] : ($hasEmail ? ['mail'] : []),
        };
    }
}
