<?php

namespace App\Services;

class PhoneNumberService
{
    /**
     * Ghana numbers only. Accepted shapes: local 0XXXXXXXXX, international
     * 233XXXXXXXXX (optionally 00233...), and the bare 9-digit subscriber
     * number XXXXXXXXX that spreadsheets produce when they drop the leading
     * zero (Ghana subscriber numbers start with 2, 3 or 5). Anything else —
     * an explicit non-233 "+" country code, a different digit count, or no
     * digits — is foreign, never guessed into a Ghanaian number.
     */
    public static function isGhanaNumber(?string $phone): bool
    {
        return self::toArkeselFormat($phone) !== null;
    }

    /** The Arkesel-ready 233XXXXXXXXX format, or null if this isn't a Ghana number. */
    public static function toArkeselFormat(?string $phone): ?string
    {
        $raw = trim($phone ?? '');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        if (preg_match('/^(?:00)?(233\d{9})$/', $digits, $match)) {
            return $match[1];
        }

        if (str_starts_with($raw, '+')) {
            return null;
        }

        if (preg_match('/^0(\d{9})$/', $digits, $match) || preg_match('/^([235]\d{8})$/', $digits, $match)) {
            return '233'.$match[1];
        }

        return null;
    }
}
