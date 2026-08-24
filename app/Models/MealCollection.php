<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealCollection extends Model
{
    protected $fillable = [
        'meal_distribution_id', 'event_registration_id', 'participant_id', 'issued_by',
        'quantity', 'was_overridden', 'override_reason', 'collected_at',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'was_overridden' => 'boolean', 'collected_at' => 'datetime'];
    }

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(MealDistribution::class, 'meal_distribution_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
