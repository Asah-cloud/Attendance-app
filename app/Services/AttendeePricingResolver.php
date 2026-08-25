<?php

namespace App\Services;

use App\Models\AttendeePricingTier;
use App\Models\Company;
use App\Models\Event;
use Illuminate\Support\Collection;

class AttendeePricingResolver
{
    public function tiersFor(Company $company, ?Event $event = null): Collection
    {
        if ($event) {
            $eventTiers = AttendeePricingTier::query()
                ->where('scope_type', AttendeePricingTier::SCOPE_EVENT)
                ->where('event_id', $event->id)
                ->orderBy('band_from')
                ->get();
            if ($eventTiers->isNotEmpty()) {
                return $eventTiers;
            }
        }

        $companyTiers = AttendeePricingTier::query()
            ->where('scope_type', AttendeePricingTier::SCOPE_COMPANY)
            ->where('company_id', $company->id)
            ->orderBy('band_from')
            ->get();
        if ($companyTiers->isNotEmpty()) {
            return $companyTiers;
        }

        if ($company->plan_key) {
            $planTiers = AttendeePricingTier::query()
                ->where('scope_type', AttendeePricingTier::SCOPE_PLAN)
                ->where('plan_key', $company->plan_key)
                ->orderBy('band_from')
                ->get();
            if ($planTiers->isNotEmpty()) {
                return $planTiers;
            }
        }

        return AttendeePricingTier::query()
            ->where('scope_type', AttendeePricingTier::SCOPE_PLATFORM)
            ->orderBy('band_from')
            ->get();
    }

    public function calculate(Company $company, int $count, ?Event $event = null): array
    {
        $tiers = $this->tiersFor($company, $event);
        $breakdown = [];
        $amountMinor = 0;

        foreach ($tiers as $tier) {
            $bandCeiling = $tier->band_to ?? PHP_INT_MAX;
            $countInBand = max(0, min($count, $bandCeiling) - $tier->band_from);

            if ($countInBand <= 0) {
                continue;
            }

            $subtotalMinor = $countInBand * $tier->rate_minor;
            $amountMinor += $subtotalMinor;

            $breakdown[] = [
                'band_from' => $tier->band_from,
                'band_to' => $tier->band_to,
                'count_in_band' => $countInBand,
                'rate_minor' => $tier->rate_minor,
                'subtotal_minor' => $subtotalMinor,
            ];
        }

        return [
            'amount_minor' => $amountMinor,
            'breakdown' => $breakdown,
        ];
    }
}
