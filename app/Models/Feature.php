<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    public const TIER_STANDARD = 'standard';

    public const TIER_ADVANCED = 'advanced';

    protected $fillable = [
        'key',
        'name',
        'tier',
        'cost_minor',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'cost_minor' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public static function purchasable()
    {
        return static::query()
            ->where('tier', self::TIER_ADVANCED)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
