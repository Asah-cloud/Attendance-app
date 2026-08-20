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

    protected $fillable = ['company_id', 'linked_user_id', 'name', 'email', 'phone', 'member_id', 'category', 'gender'];

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
