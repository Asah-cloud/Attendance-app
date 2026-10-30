<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A "Reset attendance": the check-ins it removed are kept so the reset can be undone. */
class AttendanceReset extends Model
{
    protected $fillable = ['event_id', 'user_id', 'day', 'removed_count', 'rows', 'ip_address', 'undone_at', 'undone_by'];

    protected function casts(): array
    {
        return [
            'rows' => 'array',
            'undone_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function undoneByName(): string
    {
        return User::find($this->undone_by)?->name ?? 'Someone';
    }

    public function label(): string
    {
        return $this->day === null ? 'All days' : ($this->day === 0 ? 'Arrival' : 'Day '.$this->day);
    }
}
