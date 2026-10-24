<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    // Add 'day' to this list
    protected $fillable = ['participant_id', 'event_id', 'status', 'day', 'session', 'marked_by', 'method'];

    /**
     * Only check-ins that reports count: attendees (not support staff) whose registration for
     * this event is still confirmed. The live counters and the reports both use this, so a
     * check-in by someone later cancelled drops out of every number at once.
     */
    public function scopeCountedFor(Builder $query, Event $event): Builder
    {
        return $query->where('attendances.event_id', $event->id)
            ->whereIn('attendances.participant_id', EventRegistration::query()
                ->where('event_id', $event->id)
                ->where('status', EventRegistration::STATUS_CONFIRMED)
                ->select('participant_id'))
            ->whereHas('participant', fn ($participant) => $participant->where('is_support_staff', false));
    }

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
