<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAttendeeCharge extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_VOIDED = 'voided';

    public const STATUS_PAYMENT_FAILED = 'payment_failed';

    public const STATUS_PAID = 'paid';

    public const STATUS_RECONCILED = 'reconciled';

    public const STATUS_REFUND_DUE = 'refund_due';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'event_id',
        'company_id',
        'status',
        'registered_count',
        'tier_breakdown',
        'amount_minor',
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
}
