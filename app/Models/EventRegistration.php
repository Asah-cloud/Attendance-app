<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class EventRegistration extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_WAITLISTED = 'waitlisted';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_AWAITING_CONFIRMATION = 'awaiting_confirmation';

    protected $fillable = [
        'event_id',
        'participant_id',
        'status',
        'registered_at',
        'approved_at',
        'cancelled_at',
        'source',
        'custom_answers',
        'consented_at',
        'terms_version',
        'reminder_sent_at',
        'confirmation_sent_at',
        'confirmation_reminder_sent_at',
        'accommodation_required',
        'accessibility_required',
        'accommodation_notes',
        'food_required',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'consented_at' => 'datetime',
            'custom_answers' => 'array',
            'reminder_sent_at' => 'datetime',
            'confirmation_sent_at' => 'datetime',
            'confirmation_reminder_sent_at' => 'datetime',
            'accommodation_required' => 'boolean',
            'accessibility_required' => 'boolean',
            'food_required' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EventRegistration $registration): void {
            $registration->registration_code ??= Str::random(40);
            $registration->registered_at ??= now();
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function mealCollections(): HasMany
    {
        return $this->hasMany(MealCollection::class);
    }

    public function roomAssignment(): HasOne
    {
        return $this->hasOne(RoomAssignment::class);
    }
}
