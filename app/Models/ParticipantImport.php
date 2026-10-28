<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A completed participant list import, kept so the people it added can be removed again. */
class ParticipantImport extends Model
{
    protected $fillable = ['event_id', 'user_id', 'file_name', 'added_count', 'updated_count', 'registration_ids', 'participant_ids', 'undone_at', 'undone_by'];

    protected function casts(): array
    {
        return [
            'registration_ids' => 'array',
            'participant_ids' => 'array',
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

    /** How many people undoing this import would take off the event's list. */
    public function addedToEventCount(): int
    {
        return count($this->registration_ids);
    }

    /** People who were already on this event and whose details the import changed; undo leaves those changes. */
    public function changedExistingCount(): int
    {
        // "Updated" also counts people known from other events who were newly added here.
        return max(0, $this->updated_count - (count($this->registration_ids) - count($this->participant_ids)));
    }
}
