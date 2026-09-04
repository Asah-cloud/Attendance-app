<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccommodationFloor extends Model
{
    protected $fillable = ['accommodation_block_id', 'name', 'is_accessible', 'priority', 'is_active'];

    protected $casts = ['is_accessible' => 'boolean', 'is_active' => 'boolean'];

    public function block(): BelongsTo
    {
        return $this->belongsTo(AccommodationBlock::class, 'accommodation_block_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(AccommodationRoom::class);
    }
}
