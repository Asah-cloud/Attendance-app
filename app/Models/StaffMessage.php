<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A read-only message from an admin or manager to some or all of a company's staff. */
class StaffMessage extends Model
{
    /** The accounts that can receive staff messages. */
    public const STAFF_ROLES = ['usher', 'audit_head', 'audit_staff'];

    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_EVENT = 'event';

    public const AUDIENCE_USER = 'user';

    protected $fillable = ['company_id', 'sender_id', 'audience', 'event_id', 'body', 'urgent'];

    protected function casts(): array
    {
        return ['urgent' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(StaffMessageRecipient::class);
    }

    /** Staff accounts in a company who can receive messages. */
    public static function staffIn(int $companyId): Builder
    {
        return User::query()->where('company_id', $companyId)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', self::STAFF_ROLES));
    }

    public function audienceLabel(): string
    {
        return match ($this->audience) {
            self::AUDIENCE_EVENT => 'Staff on '.($this->event?->title ?? 'a deleted event'),
            self::AUDIENCE_USER => $this->recipients->first()?->user?->name ?? 'One staff member',
            default => 'All staff',
        };
    }
}
