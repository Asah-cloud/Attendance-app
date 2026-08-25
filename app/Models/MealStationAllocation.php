<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealStationAllocation extends Model
{
    protected $fillable = ['meal_distribution_id', 'meal_station_id', 'allocated_portions'];

    protected function casts(): array
    {
        return ['allocated_portions' => 'integer'];
    }

    public function mealDistribution(): BelongsTo
    {
        return $this->belongsTo(MealDistribution::class);
    }

    public function mealStation(): BelongsTo
    {
        return $this->belongsTo(MealStation::class);
    }
}
