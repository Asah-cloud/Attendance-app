<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccommodationSite extends Model
{
    protected $fillable = ['event_id', 'name', 'address', 'check_in_instructions', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(AccommodationBlock::class);
    }
}
