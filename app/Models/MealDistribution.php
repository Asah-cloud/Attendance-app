<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealDistribution extends Model
{
    protected $fillable = ['event_id', 'name', 'total_portions', 'opens_at', 'closes_at', 'is_active'];

    protected function casts(): array
    {
        return [
            'total_portions' => 'integer',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(MealCollection::class);
    }

    public function issuedPortions(): int
    {
        return (int) ($this->collections_sum_quantity ?? $this->collections()->sum('quantity'));
    }

    public function remainingPortions(): int
    {
        return max(0, $this->total_portions - $this->issuedPortions());
    }

    public function isOpen(): bool
    {
        return $this->is_active
            && (! $this->opens_at || now()->gte($this->opens_at))
            && (! $this->closes_at || now()->lte($this->closes_at));
    }
}
