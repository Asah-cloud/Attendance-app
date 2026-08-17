<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected static function booted(): void
    {
        static::updating(function (Company $company): void {
            if ($company->isDirty('subscription_ends_at')) {
                $company->subscription_expiry_warning_sent_at = null;
                $company->subscription_expired_notice_sent_at = null;
            }
        });
    }

    protected $fillable = [
        'name',
        'email',
        'logo_path',
        'subscription_ends_at',
        'event_limit',
        'is_active',
        'plan_key',
        'plan_price_minor',
        'billing_currency',
        'payment_reference',
        'subscription_started_at',
        'subscription_auto_renews',
        'subscription_cancelled_at',
        'subscription_expiry_warning_sent_at',
        'subscription_expired_notice_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'subscription_ends_at' => 'date',
            'is_active' => 'boolean',
            'event_limit' => 'integer',
            'plan_price_minor' => 'integer',
            'subscription_started_at' => 'datetime',
            'subscription_auto_renews' => 'boolean',
            'subscription_cancelled_at' => 'datetime',
            'subscription_expiry_warning_sent_at' => 'datetime',
            'subscription_expired_notice_sent_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }
}
