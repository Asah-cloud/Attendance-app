<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealDistribution extends Model
{
    protected $fillable = [
        'event_id', 'name', 'total_portions', 'opens_at', 'closes_at', 'is_active',
        'low_stock_threshold', 'low_stock_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'total_portions' => 'integer',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'is_active' => 'boolean',
            'low_stock_threshold' => 'integer',
            'low_stock_notified_at' => 'datetime',
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

    public function entitlements(): HasMany
    {
        return $this->hasMany(MealEntitlement::class);
    }

    public function wasteLogs(): HasMany
    {
        return $this->hasMany(MealWasteLog::class);
    }

    public function stationAllocations(): HasMany
    {
        return $this->hasMany(MealStationAllocation::class);
    }

    public function issuedPortions(): int
    {
        return (int) ($this->collections_sum_quantity ?? $this->collections()->sum('quantity'));
    }

    public function remainingPortions(): int
    {
        return max(0, $this->total_portions - $this->issuedPortions());
    }

    public function allocatedPortionsFor(int $stationId): ?int
    {
        if ($this->relationLoaded('stationAllocations')) {
            return $this->stationAllocations->firstWhere('meal_station_id', $stationId)?->allocated_portions;
        }

        return $this->stationAllocations()->where('meal_station_id', $stationId)->value('allocated_portions');
    }

    public function issuedPortionsAtStation(int $stationId): int
    {
        return (int) $this->collections()->where('meal_station_id', $stationId)->sum('quantity');
    }

    public function remainingPortionsAtStation(int $stationId): ?int
    {
        $allocated = $this->allocatedPortionsFor($stationId);

        return $allocated === null ? null : max(0, $allocated - $this->issuedPortionsAtStation($stationId));
    }

    public function isOpen(): bool
    {
        return $this->is_active
            && (! $this->opens_at || now()->gte($this->opens_at))
            && (! $this->closes_at || now()->lte($this->closes_at));
    }

    public function entitlementFor(?string $category): int
    {
        if (! $category) {
            return 1;
        }

        return $this->entitlements->firstWhere('category', $category)?->portions_allowed ?? 1;
    }

    public function isLowStock(): bool
    {
        return $this->low_stock_threshold !== null && $this->remainingPortions() <= $this->low_stock_threshold;
    }
}
