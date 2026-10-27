<?php

namespace App\Models;

use App\Services\PhoneNumberService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class Participant extends Model
{
    use Notifiable;

    /** Fields recorded per event registration rather than on the person (see useEventDetails). */
    public const EVENT_FIELDS = ['category', 'room_group'];

    /**
     * The category and area of the registration this person is being read through, if any.
     * A plain property, never an attribute, so it is not saved back to the participants table.
     *
     * @var array{category: ?string, room_group: ?string}|null
     */
    public ?array $eventDetails = null;

    /** Show this event's category and area for this person (see EventRegistration::getRelationValue). */
    public function useEventDetails(EventRegistration|array $registration): static
    {
        $this->eventDetails = $registration instanceof EventRegistration
            ? ['category' => $registration->category, 'room_group' => $registration->room_group]
            : array_intersect_key($registration, array_flip(self::EVENT_FIELDS)) + ['category' => null, 'room_group' => null];

        return $this;
    }

    protected function category(): Attribute
    {
        return Attribute::get(fn ($value) => $this->eventValue('category', $value));
    }

    protected function roomGroup(): Attribute
    {
        return Attribute::get(fn ($value) => $this->eventValue('room_group', $value));
    }

    /**
     * Inside an event (loaded through a registration or an event's attendee list) the event's
     * value wins; elsewhere, such as the member directory, the person's latest value is shown.
     */
    private function eventValue(string $field, ?string $own): ?string
    {
        if ($this->eventDetails !== null) {
            return $this->eventDetails[$field];
        }

        if ($this->relationLoaded('pivot') && $this->pivot instanceof Model && $this->pivot->getTable() === 'event_registrations'
            && array_key_exists($field, $this->pivot->getAttributes())) {
            return $this->pivot->getAttribute($field);
        }

        return $own;
    }

    /** @return array<int, string> up to two Arkesel-ready numbers: primary and secondary phone. */
    public function routeNotificationForArkesel(): array
    {
        return array_values(array_filter([
            PhoneNumberService::toArkeselFormat($this->phone),
            PhoneNumberService::toArkeselFormat($this->secondary_phone),
        ]));
    }

    public function isNumberedParticipantStaff(): bool
    {
        return $this->is_support_staff
            && preg_match('/^participant\s+\d+$/i', trim($this->name)) === 1;
    }

    /** Whether any notification channel could actually reach this person right now. */
    public function hasReachableContact(): bool
    {
        return ($this->email && ! str_ends_with($this->email, '@example.invalid'))
            || (bool) $this->phone
            || (bool) $this->secondary_phone;
    }

    protected $fillable = ['company_id', 'linked_user_id', 'name', 'email', 'phone', 'secondary_phone', 'member_id', 'category', 'department', 'is_support_staff', 'staff_code', 'staff_qr_token', 'gender', 'dietary_notes', 'room_group', 'country'];

    protected function casts(): array
    {
        return ['is_support_staff' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function linkedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_user_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ParticipantAuditLog::class)->latest();
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_registrations')
            ->withPivot(['status', 'registration_code', 'registered_at', 'approved_at', 'cancelled_at', 'source'])
            ->withTimestamps();
    }
}
