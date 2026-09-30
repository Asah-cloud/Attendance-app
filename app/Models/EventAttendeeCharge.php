<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAttendeeCharge extends Model
{
    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_VOIDED = 'voided';

    public const STATUS_PAYMENT_FAILED = 'payment_failed';

    public const STATUS_PAID = 'paid';

    public const STATUS_RECONCILED = 'reconciled';

    public const STATUS_REFUND_DUE = 'refund_due';

    public const STATUS_REFUNDED = 'refunded';

    /** Statuses reached only after payment succeeded — used to gate paid features. */
    public const PAID_STATUSES = [
        self::STATUS_PAID,
        self::STATUS_RECONCILED,
        self::STATUS_REFUND_DUE,
        self::STATUS_REFUNDED,
    ];

    protected $fillable = [
        'event_id',
        'company_id',
        'status',
        'registered_count',
        'tier_breakdown',
        'amount_minor',
        'features_amount_minor',
        'feature_breakdown',
        'discount_minor',
        'discount_reason',
        'invoice_number',
        'reviewed_by',
        'reviewed_at',
        'invoice_emailed_at',
        'grandfathered',
        'currency',
        'payment_reference',
        'paid_at',
        'finalized_at',
        'checked_in_count',
        'refund_breakdown',
        'refund_amount_minor',
        'reconciled_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'registered_count' => 'integer',
            'tier_breakdown' => 'array',
            'amount_minor' => 'integer',
            'features_amount_minor' => 'integer',
            'feature_breakdown' => 'array',
            'discount_minor' => 'integer',
            'reviewed_at' => 'datetime',
            'invoice_emailed_at' => 'datetime',
            'grandfathered' => 'boolean',
            'paid_at' => 'datetime',
            'finalized_at' => 'datetime',
            'checked_in_count' => 'integer',
            'refund_breakdown' => 'array',
            'refund_amount_minor' => 'integer',
            'reconciled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isAwaitingReview(): bool
    {
        return $this->status === self::STATUS_PENDING_REVIEW;
    }

    public function hasApprovedInvoice(): bool
    {
        return $this->invoice_number !== null;
    }

    /**
     * Whether this bill can still get (or needs) a formal, numbered invoice —
     * true for a fresh manager/admin request, but also for a bill that was
     * created automatically at the event's date and never went through
     * review. False once it already has one, or once it's paid/void/refunded.
     */
    public function needsInvoice(): bool
    {
        return $this->invoice_number === null && ! in_array($this->status, [
            self::STATUS_VOIDED,
            self::STATUS_PAID,
            self::STATUS_RECONCILED,
            self::STATUS_REFUND_DUE,
            self::STATUS_REFUNDED,
        ], true);
    }
}
