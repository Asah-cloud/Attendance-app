<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealWasteLog extends Model
{
    protected $fillable = ['meal_distribution_id', 'quantity', 'reason', 'logged_by', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(MealDistribution::class, 'meal_distribution_id');
    }

    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
