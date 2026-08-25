<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealEntitlement extends Model
{
    protected $fillable = ['meal_distribution_id', 'category', 'portions_allowed'];

    protected function casts(): array
    {
        return ['portions_allowed' => 'integer'];
    }

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(MealDistribution::class, 'meal_distribution_id');
    }
}
