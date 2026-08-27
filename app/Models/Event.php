<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Event extends Model
{
    protected $attributes = [
        'registration_enabled' => false,
        'registration_requires_approval' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (Event $event): void {
            if ($event->slug) {
                return;
            }

            $base = Str::slug($event->title) ?: 'event';
            $slug = $base;
            $suffix = 2;
            while (static::query()->where('slug', $slug)->exists()) {
                $slug = $base.'-'.$suffix++;
            }
            $event->slug = $slug;
        });
    }

    protected $fillable = [
        'company_id',
        'title',
        'slug',
        'day',
        'event_date',
        'end_date',
        'has_arrival_session',
        'arrival_date',
        'description',
        'location',
        'logo_path',
        'flyer_path',
        'registration_enabled',
        'registration_opens_at',
        'registration_closes_at',
        'registration_capacity',
        'registration_requires_approval',
        'registration_terms',
        'registration_terms_version',
        'confirmation_message',
        'cancelled_at',
        'badge_size',
        'badge_design',
        'badge_category_colors',
        'badge_layout',
        'badge_image_path',
        'badge_primary_color',
        'badge_accent_color',
        'badge_image_position_x',
        'badge_image_position_y',
    ];

    protected $casts = [
        'event_date' => 'date',
        'end_date' => 'date',
        'has_arrival_session' => 'boolean',
        'arrival_date' => 'date',
        'registration_enabled' => 'boolean',
        'registration_opens_at' => 'datetime',
        'registration_closes_at' => 'datetime',
        'registration_capacity' => 'integer',
        'registration_requires_approval' => 'boolean',
        'cancelled_at' => 'datetime',
        'badge_category_colors' => 'array',
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

    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }

    public function attendeeCharge(): HasOne
    {
        return $this->hasOne(EventAttendeeCharge::class);
    }

    public function checkedInParticipantCount(): int
    {
        return $this->attendances()->distinct('participant_id')->count('participant_id');
    }

    public function registrationFields(): HasMany
    {
        return $this->hasMany(EventRegistrationField::class)->orderBy('display_order');
    }

    public function mealDistributions(): HasMany
    {
        return $this->hasMany(MealDistribution::class);
    }

    public function mealStations(): HasMany
    {
        return $this->hasMany(MealStation::class);
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

    public function activeAttendanceSession(): int
    {
        $session = (int) ($this->day ?? 1);

        return $session === 0 && $this->has_arrival_session ? 0 : max(1, min($this->totalDays(), $session));
    }

    public function attendanceSessionLabel(int|string $session): string
    {
        if ($session === 'all') {
            return 'All sessions';
        }

        return (int) $session === 0 ? 'Arrival' : 'Day '.(int) $session;
    }

    public function canMarkAttendanceForDay(int $day): bool
    {
        if ($day === 0) {
            return $this->has_arrival_session
                && ! $this->cancelled_at
                && $this->arrival_date
                && now()->startOfDay()->gte($this->arrival_date->startOfDay());
        }

        return $this->status === 'active'
            && $day >= 1
            && $day <= $this->currentDay();
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['closed', 'cancelled'], true);
    }

    public function registrationIsOpen(): bool
    {
        return ! $this->cancelled_at
            && $this->registration_enabled
            && (! $this->registration_opens_at || now()->gte($this->registration_opens_at))
            && (! $this->registration_closes_at || now()->lte($this->registration_closes_at));
    }
}
