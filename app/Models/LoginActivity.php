<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One sign-in or failed sign-in attempt, shown to platform admins in the sign-in bell. */
class LoginActivity extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    /** Failed attempts for one email within FAILURE_WINDOW_MINUTES that raise an alert. */
    public const FAILURE_ALERT_THRESHOLD = 5;

    public const FAILURE_WINDOW_MINUTES = 10;

    public const KEEP_DAYS = 90;

    protected $fillable = ['user_id', 'company_id', 'email', 'roles', 'successful', 'alert', 'ip_address', 'user_agent'];

    protected function casts(): array
    {
        return ['successful' => 'boolean', 'alert' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** What the bell shows: sign-ins and failed-attempt alerts, never the viewing admin's own sign-ins. */
    public function scopeForBell(Builder $query, User $admin): Builder
    {
        return $query->where(fn ($q) => $q->where('successful', true)->orWhere('alert', true))
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $admin->id));
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(self::KEEP_DAYS));
    }

    /** "Manager", "Audit Staff", "Response viewer", ... */
    public function roleLabel(): string
    {
        return collect(explode(',', (string) $this->roles))->filter()
            ->map(fn (string $role) => $role === 'form_viewer' ? 'Response viewer' : str($role)->replace('_', ' ')->title()->toString())
            ->join(', ') ?: 'No role';
    }

    /** "Chrome on Windows", "Safari on iPhone", ... - enough to spot an unfamiliar device. */
    public function deviceLabel(): string
    {
        $agent = (string) $this->user_agent;
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Unknown browser',
        };
        $device = match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS X') => 'Mac',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'unknown device',
        };

        return "{$browser} on {$device}";
    }

    /** The one-line summary used in the pop-up and the bell. */
    public function summary(): string
    {
        if (! $this->successful) {
            return self::FAILURE_ALERT_THRESHOLD.' failed sign-in attempts for '.$this->email.' in '.self::FAILURE_WINDOW_MINUTES.' minutes';
        }

        return ($this->user?->name ?? $this->email).' ('.$this->roleLabel().($this->company ? ' · '.$this->company->name : '').') signed in';
    }
}
