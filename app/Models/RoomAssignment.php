<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomAssignment extends Model
{
    protected $fillable = ['event_registration_id', 'accommodation_room_id', 'status', 'method', 'is_locked', 'allocation_reason', 'assigned_by', 'assigned_at', 'notification_sent_at', 'checked_in_at', 'checked_in_by', 'checked_out_at', 'checked_out_by'];

    protected $casts = ['is_locked' => 'boolean', 'assigned_at' => 'datetime', 'notification_sent_at' => 'datetime', 'checked_in_at' => 'datetime', 'checked_out_at' => 'datetime'];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(AccommodationRoom::class, 'accommodation_room_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }
}
