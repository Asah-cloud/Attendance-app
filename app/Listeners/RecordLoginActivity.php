<?php

namespace App\Listeners;

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Events\Dispatcher;

/**
 * Records sign-ins and failed sign-in attempts for the platform admins' sign-in bell.
 * Registered as a subscriber in AppServiceProvider; its methods deliberately don't start
 * with "handle", which Laravel's listener discovery would register a second time.
 */
class RecordLoginActivity
{
    public function recordLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        LoginActivity::create($this->requestDetails() + [
            'user_id' => $event->user->id,
            'company_id' => $event->user->company_id,
            'email' => $event->user->email,
            'roles' => $event->user->getRoleNames()->join(','),
            'successful' => true,
        ]);
    }

    public function recordFailure(Failed $event): void
    {
        $email = strtolower(trim((string) ($event->credentials['email'] ?? ''))) ?: null;
        $user = $event->user instanceof User ? $event->user : null;

        $attempt = LoginActivity::create($this->requestDetails() + [
            'user_id' => $user?->id,
            'company_id' => $user?->company_id,
            'email' => $email,
            'roles' => $user?->getRoleNames()->join(','),
            'successful' => false,
        ]);

        // Every FAILURE_ALERT_THRESHOLD-th failure for one email within the window raises an alert,
        // so a long guessing run keeps showing up without one alert per attempt.
        $recentFailures = LoginActivity::where('successful', false)->where('email', $email)
            ->where('created_at', '>=', now()->subMinutes(LoginActivity::FAILURE_WINDOW_MINUTES))->count();
        if ($email && $recentFailures % LoginActivity::FAILURE_ALERT_THRESHOLD === 0) {
            $attempt->update(['alert' => true]);
        }
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'recordLogin',
            Failed::class => 'recordFailure',
        ];
    }

    /** @return array{ip_address: ?string, user_agent: ?string} */
    private function requestDetails(): array
    {
        return [
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 1000) ?: null,
        ];
    }
}
