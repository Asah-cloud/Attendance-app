<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealCollectionAudit extends Model
{
    protected $fillable = [
        'event_id', 'meal_distribution_id', 'event_registration_id', 'participant_id',
        'performed_by', 'action', 'quantity_change', 'reason', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['quantity_change' => 'integer', 'occurred_at' => 'datetime'];
    }

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(MealDistribution::class, 'meal_distribution_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
