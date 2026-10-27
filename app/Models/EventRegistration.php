<?php

namespace App\Models;

use App\Services\StaffCheckInService;
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
        'category',
        'room_group',
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
        'duplicate_dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'duplicate_dismissed_at' => 'datetime',
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
            $registration->management_token ??= Str::random(40);
            $registration->registered_at ??= now();

            // A registration made without a category or area starts from the person's current
            // ones; a value given explicitly (even blank) is kept as this event's.
            $missing = array_diff(Participant::EVENT_FIELDS, array_keys($registration->getAttributes()));
            if ($missing !== [] && ($person = Participant::query()->find($registration->participant_id))) {
                foreach ($missing as $field) {
                    $registration->setAttribute($field, $person->getRawOriginal($field));
                }
            }
        });

        static::saved(function (EventRegistration $registration): void {
            if ($registration->status !== self::STATUS_CONFIRMED || ! $registration->participant?->is_support_staff) {
                return;
            }

            if (Attendance::query()->where('participant_id', $registration->participant_id)->where('day', -1)->exists()) {
                app(StaffCheckInService::class)->checkIn($registration->participant_id);
            }
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

    /**
     * Whenever a person is read through this registration ($registration->participant), their
     * category and area are this event's, not whatever a later import set on the person.
     */
    public function getRelationValue($key)
    {
        $value = parent::getRelationValue($key);

        if ($key === 'participant' && $value instanceof Participant) {
            $value->useEventDetails($this);
        }

        return $value;
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
