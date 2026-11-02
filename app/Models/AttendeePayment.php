<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** A payment an attendee makes on a form or the registration form (see AttendeePaymentService). */
class AttendeePayment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id', 'event_id', 'payable_type', 'payable_id', 'reference', 'amount_minor',
        'platform_fee_minor', 'currency', 'status', 'payer_email', 'channel', 'paid_at', 'payment_option',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'platform_fee_minor' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function money(?int $minor, string $currency = 'GHS'): string
    {
        return ($currency === 'GHS' ? 'GH₵ ' : $currency.' ').number_format(($minor ?? 0) / 100, 2);
    }
}
