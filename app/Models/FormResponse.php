<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class FormResponse extends Model
{
    protected $fillable = [
        'form_id',
        'answers',
        'ip_address',
        'payment_status',
        'amount_paid_minor',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'amount_paid_minor' => 'integer',
        ];
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(AttendeePayment::class, 'payable');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
