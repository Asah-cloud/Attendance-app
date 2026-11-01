<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SignInCodeNotification;
use Illuminate\Support\Facades\Hash;

/**
 * Six-digit codes emailed to staff who sign in without a password (users.signs_in_with_code).
 * A user holds one code at a time, stored hashed; it works once, and stops working after it
 * expires or after MAX_ATTEMPTS wrong tries, when they ask for a new one.
 */
class SignInCode
{
    /** A code sent when the account is added: long enough for the email to be opened later. */
    public const INVITE_MINUTES = 7 * 24 * 60;

    /** A code asked for on the sign-in page. */
    public const LOGIN_MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    /** Make a new code for the user (replacing any earlier one) and email it. */
    public function send(User $user, bool $invite = false): void
    {
        $minutes = $invite ? self::INVITE_MINUTES : self::LOGIN_MINUTES;

        // Sent straight away rather than queued: the person is waiting at the sign-in page.
        $user->notifyNow(new SignInCodeNotification($this->issue($user, $minutes), $minutes, $invite));
    }

    /** A code for a signed-in user to set their own password with, in place of a current password. */
    public function sendForPassword(User $user): void
    {
        $user->notifyNow(new SignInCodeNotification($this->issue($user, self::LOGIN_MINUTES), self::LOGIN_MINUTES, forPassword: true));
    }

    private function issue(User $user, int $minutes): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->forceFill([
            'sign_in_code' => Hash::make($code),
            'sign_in_code_expires_at' => now()->addMinutes($minutes),
            'sign_in_code_attempts' => 0,
        ])->save();

        return $code;
    }

    /** Whether the code is right; a right code is used up, a wrong one counts as a try. */
    public function check(User $user, string $code): bool
    {
        if (! $user->signs_in_with_code || ! $user->sign_in_code || ! $user->sign_in_code_expires_at?->isFuture()
            || $user->sign_in_code_attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! Hash::check(preg_replace('/\D+/', '', $code), $user->sign_in_code)) {
            $user->forceFill(['sign_in_code_attempts' => $user->sign_in_code_attempts + 1])->save();

            return false;
        }

        $user->forceFill(['sign_in_code' => null, 'sign_in_code_expires_at' => null, 'sign_in_code_attempts' => 0])->save();

        return true;
    }
}
