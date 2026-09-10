<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealStation extends Model
{
    protected $fillable = ['event_id', 'name'];

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meal_station_staff')->withTimestamps();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(MealStationAllocation::class);
    }
}
