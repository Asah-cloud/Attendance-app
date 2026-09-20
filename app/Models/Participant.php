<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class Participant extends Model
{
    use Notifiable;

    public function routeNotificationForArkesel(): ?string
    {
        $phone = preg_replace('/\D+/', '', $this->phone ?? '') ?? '';
        if ($phone === '') {
            return null;
        }

        if (str_starts_with($phone, '233')) {
            return $phone;
        }

        return '233'.ltrim($phone, '0');
    }

    public function isNumberedParticipantStaff(): bool
    {
        return $this->is_support_staff
            && preg_match('/^participant\s+\d+$/i', trim($this->name)) === 1;
    }

    protected $fillable = ['company_id', 'linked_user_id', 'name', 'email', 'phone', 'member_id', 'category', 'department', 'is_support_staff', 'staff_code', 'staff_qr_token', 'gender', 'dietary_notes', 'room_group'];

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
