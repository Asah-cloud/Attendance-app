<?php

namespace App\Support;

class AccommodationPriority
{
    public const DEFAULT = 100;

    /** Human label => stored sort value (lower fills first). */
    public const TIERS = [
        'Fill first' => 25,
        'Normal' => 100,
        'Fill last' => 500,
        'Never auto-fill' => 900,
    ];

    public static function label(int|string|null $value): string
    {
        $value = (int) ($value ?? self::DEFAULT);
        $match = array_search($value, self::TIERS, true);

        return $match !== false ? $match : "Custom ({$value})";
    }

    /**
     * Options to render in a <select>, keeping any non-standard current value visible.
     *
     * @return array<string, int> label => value
     */
    public static function options(int|string|null $current): array
    {
        $current = (int) ($current ?? self::DEFAULT);
        $tiers = self::TIERS;

        if (! in_array($current, $tiers, true)) {
            $tiers["Custom ({$current})"] = $current;
        }

        return $tiers;
    }
}
