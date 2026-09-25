<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventFeature extends Model
{
    protected $fillable = [
        'event_id',
        'feature_key',
        'name',
        'cost_minor',
    ];

    protected function casts(): array
    {
        return [
            'cost_minor' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
