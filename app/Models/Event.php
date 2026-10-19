<?php

namespace App\Models;

use App\Notifications\Channels\ArkeselChannel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
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
            $event->slug ??= static::uniqueSlug($event->title);
        });

        // The public link name follows the title, so a renamed event's links don't keep
        // its old name. The old link name is kept as a redirect (see findBySlug()).
        static::updating(function (Event $event): void {
            if ($event->isDirty('title') && ! $event->isDirty('slug')) {
                $event->slug = static::uniqueSlug($event->title, $event->id);
            }
        });

        static::updated(function (Event $event): void {
            $old = $event->getOriginal('slug');
            if (! $event->wasChanged('slug') || blank($old)) {
                return;
            }

            EventSlugRedirect::updateOrCreate(['slug' => $old], ['event_id' => $event->id]);
            EventSlugRedirect::where('slug', $event->slug)->delete();
        });
    }

    /** A link name made from the title that no other event uses now or used before a rename. */
    public static function uniqueSlug(?string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug((string) $title) ?: 'event';
        $slug = $base;
        $suffix = 2;
        while (static::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()
            || EventSlugRedirect::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('event_id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * The event with this link name. An old link name from before a rename finds the event
     * too: a page visit is redirected to the same page under the current name, and anything
     * else (such as a form submitted from a page opened before the rename) just proceeds.
     */
    public static function findBySlug(string $slug): ?self
    {
        if ($event = static::where('slug', $slug)->first()) {
            return $event;
        }

        $event = EventSlugRedirect::where('slug', $slug)->first()?->event;
        if ($event && request()->isMethod('GET')) {
            $segments = array_map(fn (string $segment) => $segment === $slug ? $event->slug : $segment, request()->segments());
            $query = request()->getQueryString();

            throw new HttpResponseException(redirect()->to(url(implode('/', $segments)).($query ? '?'.$query : ''), 301));
        }

        return $event;
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $field === 'slug' ? static::findBySlug((string) $value) : parent::resolveRouteBinding($value, $field);
    }

    protected $fillable = [
        'company_id',
        'title',
        'slug',
        'day',
        'session',
        'event_date',
        'end_date',
        'has_arrival_session',
        'arrival_date',
        'accommodation_enabled',
        'self_check_in_enabled',
        'accommodation_published',
        'accommodation_self_select_closes_at',
        'food_registration_required',
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
        'badge_fields',
        'badge_font',
        'badge_name_format',
        'staff_badge_settings',
        'automatic_attendee_email',
        'automatic_attendee_sms',
    ];

    protected $casts = [
        'event_date' => 'date',
        'end_date' => 'date',
        'has_arrival_session' => 'boolean',
        'arrival_date' => 'date',
        'accommodation_enabled' => 'boolean',
        'self_check_in_enabled' => 'boolean',
        'accommodation_published' => 'boolean',
        'accommodation_self_select_closes_at' => 'datetime',
        'food_registration_required' => 'boolean',
        'registration_enabled' => 'boolean',
        'registration_opens_at' => 'datetime',
        'registration_closes_at' => 'datetime',
        'registration_capacity' => 'integer',
        'registration_requires_approval' => 'boolean',
        'cancelled_at' => 'datetime',
        'badge_category_colors' => 'array',
        'badge_fields' => 'array',
        'staff_badge_settings' => 'array',
        'automatic_attendee_email' => 'boolean',
        'automatic_attendee_sms' => 'boolean',
    ];

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function eventSessions(): HasMany
    {
        return $this->hasMany(EventSession::class);
    }

    /**
     * Every session that exists for a given day, always including the implicit
     * session 1 (which has no stored row). Stored rows only ever represent
     * additional sessions (session number >= 2).
     *
     * @return Collection<int, array{session: int, label: ?string}>
     */
    public function sessionsForDay(int $day): Collection
    {
        $stored = $this->eventSessions()
            ->where('day', $day)
            ->orderBy('session')
            ->get(['session', 'label']);

        return collect([['session' => 1, 'label' => null]])
            ->concat($stored->map(fn (EventSession $s) => ['session' => $s->session, 'label' => $s->label]))
            ->unique('session')
            ->sortBy('session')
            ->values();
    }

    public function sessionCountForDay(int $day): int
    {
        return max(1, (int) ($this->eventSessions()->where('day', $day)->max('session') ?? 1));
    }

    public function activeSessionNumber(): int
    {
        return max(1, min($this->sessionCountForDay($this->activeAttendanceSession()), (int) ($this->session ?? 1)));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function attendeeNotificationChannels(): array
    {
        return array_values(array_filter([
            $this->automatic_attendee_email ? 'mail' : null,
            $this->automatic_attendee_sms ? ArkeselChannel::class : null,
        ]));
    }

    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }

    public function customMessages(): HasMany
    {
        return $this->hasMany(CustomMessage::class);
    }

    public function attendeeCharge(): HasOne
    {
        return $this->hasOne(EventAttendeeCharge::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(EventFeature::class);
    }

    /**
     * Whether this event has bought and paid for the given advanced feature.
     * Standard functionality (attendance, check-in, basic reports) is never
     * gated and doesn't need this check.
     */
    public function hasFeature(string $key): bool
    {
        $charge = $this->attendeeCharge;

        if (! $charge || ! in_array($charge->status, EventAttendeeCharge::PAID_STATUSES, true)) {
            return false;
        }

        if ($charge->grandfathered) {
            return true;
        }

        return $this->features->contains('feature_key', $key);
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

    public function accommodationSites(): HasMany
    {
        return $this->hasMany(AccommodationSite::class);
    }

    public function accommodationSelfSelectOpen(): bool
    {
        return $this->accommodation_enabled
            && $this->accommodation_self_select_closes_at !== null
            && $this->accommodation_self_select_closes_at->isFuture();
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

    /** Confirmed registrations, excluding event support staff - see confirmedStaff(). */
    public function confirmedParticipants(): BelongsToMany
    {
        return $this->registeredParticipants()
            ->wherePivot('status', EventRegistration::STATUS_CONFIRMED)
            ->where('is_support_staff', false);
    }

    public function confirmedStaff(): BelongsToMany
    {
        return $this->registeredParticipants()
            ->wherePivot('status', EventRegistration::STATUS_CONFIRMED)
            ->where('is_support_staff', true);
    }

    public function arrivedParticipants(): BelongsToMany
    {
        return $this->confirmedParticipants()
            ->whereHas('attendances', fn ($query) => $query
                ->where('event_id', $this->id)
                ->where('day', 0));
    }

    public function attendanceEligibleParticipants(): BelongsToMany
    {
        return $this->has_arrival_session
            ? $this->arrivedParticipants()
            : $this->confirmedParticipants();
    }

    /**
     * Count checked-in support staff whose names follow "Participant <number>".
     *
     * A staff scan may use the dedicated check-in marker (-1) or the regular
     * attendance scanner on Day 1. Either makes presence persist for the event.
     *
     * @return Collection<int, int>
     */
    public function persistentParticipantStaffIds(): Collection
    {
        return $this->confirmedStaff()
            ->whereHas('attendances', fn ($query) => $query
                ->where('event_id', $this->id)
                ->whereIn('day', [-1, 1]))
            ->whereRaw('LOWER(TRIM(participants.name)) LIKE ?', ['participant %'])
            ->get(['participants.id', 'participants.name', 'participants.is_support_staff'])
            ->filter(fn (Participant $participant) => $participant->isNumberedParticipantStaff())
            ->pluck('id');
    }

    public function persistentParticipantStaffCount(): int
    {
        return $this->persistentParticipantStaffIds()->count();
    }

    public function participantHasArrived(int $participantId): bool
    {
        return ! $this->has_arrival_session || $this->attendances()
            ->where('participant_id', $participantId)
            ->where('day', 0)
            ->exists();
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

        return max(1, min($this->totalDays(), $session));
    }

    public function attendanceSessionLabel(int|string $day, int $session = 1): string
    {
        if ($day === 'all') {
            return 'All sessions';
        }

        $label = (int) $day === 0 ? 'Arrival' : 'Day '.(int) $day;

        return $session > 1 ? $label.' · Session '.$session : $label;
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
