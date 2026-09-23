<?php

namespace App\Services;

class PhoneNumberService
{
    /**
     * Ghana numbers only: local 0XXXXXXXXX (10 digits) or international
     * 233XXXXXXXXX (12 digits). Anything else — a different country code,
     * a different digit count, or no digits at all — is treated as foreign
     * rather than guessed at, so a mistyped or international number is
     * never silently forced into a fake Ghanaian one.
     */
    public static function isGhanaNumber(?string $phone): bool
    {
        $digits = preg_replace('/\D+/', '', $phone ?? '') ?? '';

        return (bool) preg_match('/^(0\d{9}|233\d{9})$/', $digits);
    }

    /** The Arkesel-ready 233XXXXXXXXX format, or null if this isn't a Ghana number. */
    public static function toArkeselFormat(?string $phone): ?string
    {
        if (! self::isGhanaNumber($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return str_starts_with($digits, '233') ? $digits : '233'.ltrim($digits, '0');
    }
}
