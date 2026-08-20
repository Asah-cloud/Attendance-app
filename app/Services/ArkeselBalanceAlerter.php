<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ArkeselBalanceLow;
use Illuminate\Support\Facades\Cache;

class ArkeselBalanceAlerter
{
    private const THROTTLE_KEY = 'arkesel-low-balance-alert-sent-at';

    /**
     * Alert platform admins that Arkesel is rejecting SMS sends (e.g. an
     * out-of-balance account), at most once every few hours - a bulk send
     * can fail the same way dozens of times in a row, and admins only need
     * to hear about it once.
     */
    public function alertOnce(string $providerMessage): void
    {
        if (Cache::has(self::THROTTLE_KEY)) {
            return;
        }
        Cache::put(self::THROTTLE_KEY, now(), now()->addHours(6));

        User::role('admin')->whereNotNull('email')->each(
            fn (User $admin) => $admin->notify(new ArkeselBalanceLow($providerMessage))
        );
    }
}
