<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'key',
        'name',
        'price_minor',
        'event_limit',
        'participant_limit',
        'description',
        'features',
        'featured',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'event_limit' => 'integer',
            'participant_limit' => 'integer',
            'features' => 'array',
            'featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function toDisplayArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'price_minor' => $this->price_minor,
            'event_limit' => $this->event_limit,
            'participant_limit' => $this->participant_limit,
            'description' => $this->description,
            'features' => $this->features ?? [],
            'featured' => $this->featured,
        ];
    }

    /**
     * All plans as an associative array keyed by plan key, matching the
     * shape the old config('plans.plans') array used to have — kept so
     * views and controllers that expect $plans[$key]['name'] etc. don't
     * need to change.
     */
    public static function allKeyed(): array
    {
        return static::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (self $plan) => [$plan->key => $plan->toDisplayArray()])
            ->all();
    }

    public static function arrayByKey(string $key): array
    {
        $plan = static::query()->where('key', $key)->first();
        abort_unless($plan, 404);

        return $plan->toDisplayArray();
    }
}
