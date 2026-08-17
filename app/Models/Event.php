<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $attributes = [
        'registration_enabled' => false,
        'registration_requires_approval' => false,
    ];

    protected $fillable = [
        'company_id',
        'title',
        'day',
        'event_date',
        'end_date',
        'description',
        'location',
        'registration_enabled',
        'registration_opens_at',
        'registration_closes_at',
        'registration_capacity',
        'registration_requires_approval',
        'registration_terms',
        'registration_terms_version',
        'cancelled_at',
    ];

    protected $casts = [
        'event_date' => 'date',
        'end_date' => 'date',
        'registration_enabled' => 'boolean',
        'registration_opens_at' => 'datetime',
        'registration_closes_at' => 'datetime',
        'registration_capacity' => 'integer',
        'registration_requires_approval' => 'boolean',
        'cancelled_at' => 'datetime',
    ];

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function registrationFields(): HasMany
    {
        return $this->hasMany(EventRegistrationField::class)->orderBy('display_order');
    }

    public function ensureSystemRegistrationFields(): void
    {
        foreach (EventRegistrationField::SYSTEM_FIELDS as $key => $definition) {
            $this->registrationFields()->firstOrCreate(
                ['field_key' => $key],
                $definition + ['is_system' => true, 'is_required' => true, 'is_active' => true]
            );
        }
    }

    public function registeredParticipants(): BelongsToMany
    {
        return $this->belongsToMany(Participant::class, 'event_registrations')
            ->withPivot([
                'status',
                'registration_code',
                'registered_at',
                'approved_at',
                'cancelled_at',
                'source',
            ])
            ->withTimestamps();
    }

    public function confirmedParticipants(): BelongsToMany
    {
        return $this->registeredParticipants()
            ->wherePivot('status', EventRegistration::STATUS_CONFIRMED);
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_staff')->withTimestamps();
    }

    public function getStatusAttribute()
    {
        if ($this->cancelled_at) {
            return 'cancelled';
        }

        $today = now()->startOfDay();
        $start = Carbon::parse($this->event_date)->startOfDay();

        $end = $this->end_date
            ? Carbon::parse($this->end_date)->startOfDay()
            : $start;

        if ($today->lt($start)) {
            return 'upcoming';
        }

        if ($today->lte($end)) {
            return 'active';
        }

        return 'closed';
    }

    public function totalDays(): int
    {
        $start = Carbon::parse($this->event_date)->startOfDay();
        $end = Carbon::parse($this->end_date ?? $this->event_date)->startOfDay();

        return (int) $start->diffInDays($end) + 1;
    }

    public function currentDay(): int
    {
        $start = Carbon::parse($this->event_date)->startOfDay();

        return (int) min($this->totalDays(), max(1, $start->diffInDays(now()->startOfDay()) + 1));
    }

    public function canMarkAttendanceForDay(int $day): bool
    {
        return $this->status === 'active'
            && $day >= 1
            && $day <= $this->currentDay();
    }

    public function registrationIsOpen(): bool
    {
        return ! $this->cancelled_at
            && $this->registration_enabled
            && (! $this->registration_opens_at || now()->gte($this->registration_opens_at))
            && (! $this->registration_closes_at || now()->lte($this->registration_closes_at));
    }
}
