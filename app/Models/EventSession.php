<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSession extends Model
{
    protected $fillable = ['event_id', 'day', 'session', 'label'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
