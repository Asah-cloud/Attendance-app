<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'email_verified_at', 'password', 'phone', 'member_id', 'category', 'role', 'company_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    public function routeNotificationForArkesel(): ?string
    {
        $phone = preg_replace('/\D+/', '', $this->phone ?? '') ?? '';

        return $phone === '' ? null : (str_starts_with($phone, '233') ? $phone : '233'.ltrim($phone, '0'));
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function linkedParticipant(): HasOne
    {
        return $this->hasOne(Participant::class, 'linked_user_id');
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_staff')->withTimestamps();
    }
}
