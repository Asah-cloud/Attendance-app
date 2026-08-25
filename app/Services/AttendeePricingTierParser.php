<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AttendeePricingTierParser
{
    /**
     * Parse newline-delimited "from-to:rate" lines (rate in major currency
     * units, e.g. "0-100:2.00") into rows ready for AttendeePricingTier
     * inserts. The last line must be unbounded ("1000-:0.50") so every
     * attendee count above the highest configured band still has a rate.
     */
    public function parse(string $text): array
    {
        $lines = collect(preg_split('/\r\n|\r|\n/', trim($text)))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['tiers' => 'Add at least one pricing tier.']);
        }

        $rows = $lines->map(function ($line, $index) use ($lines) {
            if (! preg_match('/^(\d+)-(\d*):(\d+(?:\.\d{1,2})?)$/', $line, $matches)) {
                throw ValidationException::withMessages(['tiers' => "Line \"{$line}\" is not in the format from-to:rate (e.g. 0-100:2.00 or 1000-:0.50)."]);
            }

            $isLast = $index === $lines->count() - 1;
            $bandTo = $matches[2] === '' ? null : (int) $matches[2];

            if ($bandTo === null && ! $isLast) {
                throw ValidationException::withMessages(['tiers' => 'Only the last tier may be unbounded (leave the upper bound empty).']);
            }
            if ($bandTo !== null && $isLast) {
                throw ValidationException::withMessages(['tiers' => 'The last tier must be unbounded (leave the upper bound empty), e.g. 1000-:0.50.']);
            }

            return [
                'band_from' => (int) $matches[1],
                'band_to' => $bandTo,
                'rate_minor' => (int) round(((float) $matches[3]) * 100),
            ];
        })->values();

        if ($rows->first()['band_from'] !== 0) {
            throw ValidationException::withMessages(['tiers' => 'The first tier must start from 0.']);
        }

        for ($i = 1; $i < $rows->count(); $i++) {
            if ($rows[$i]['band_from'] !== $rows[$i - 1]['band_to']) {
                throw ValidationException::withMessages(['tiers' => 'Tiers must be contiguous and ascending (each band must start where the previous one ended).']);
            }
        }

        return $rows->all();
    }

    public function format(Collection $tiers): string
    {
        return $tiers->map(function ($tier) {
            $rate = number_format($tier->rate_minor / 100, 2, '.', '');

            return $tier->band_from.'-'.($tier->band_to ?? '').':'.$rate;
        })->implode("\n");
    }
}
