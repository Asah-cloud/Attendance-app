<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A link name an event used before it was renamed. */
class EventSlugRedirect extends Model
{
    protected $fillable = ['event_id', 'slug'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
