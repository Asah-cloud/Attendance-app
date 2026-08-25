<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    public const BILLING_MODE_SUBSCRIPTION = 'subscription';

    public const BILLING_MODE_PAY_PER_EVENT = 'pay_per_event';

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
        'email_from_name',
        'email_from_address',
        'email_sender_status',
        'sms_sender_id',
        'sms_sender_status',
        'resend_domain_id',
        'resend_domain_name',
        'resend_domain_status',
        'resend_domain_records',
        'resend_setup_error',
        'resend_last_checked_at',
        'resend_setup_started_at',
        'resend_first_reminder_sent_at',
        'resend_delayed_notice_sent_at',
        'resend_failure_notice_sent_at',
        'resend_verified_notice_sent_at',
        'subscription_ends_at',
        'event_limit',
        'is_active',
        'billing_mode',
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
            'resend_domain_records' => 'array',
            'resend_last_checked_at' => 'datetime',
            'resend_setup_started_at' => 'datetime',
            'resend_first_reminder_sent_at' => 'datetime',
            'resend_delayed_notice_sent_at' => 'datetime',
            'resend_failure_notice_sent_at' => 'datetime',
            'resend_verified_notice_sent_at' => 'datetime',
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

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function attendeeCharges(): HasMany
    {
        return $this->hasMany(EventAttendeeCharge::class);
    }

    public function attendeePricingTiers(): HasMany
    {
        return $this->hasMany(AttendeePricingTier::class);
    }

    public function approvedEmailFromAddress(): ?string
    {
        return $this->email_sender_status === 'approved' ? $this->email_from_address : null;
    }

    public function approvedSmsSenderId(): ?string
    {
        return $this->sms_sender_status === 'approved' ? $this->sms_sender_id : null;
    }

    public function isPayPerEvent(): bool
    {
        return $this->billing_mode === self::BILLING_MODE_PAY_PER_EVENT;
    }
}
