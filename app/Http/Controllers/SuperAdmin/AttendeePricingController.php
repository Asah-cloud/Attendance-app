<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AttendeePricingTier;
use App\Services\AttendeePricingTierParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AttendeePricingController extends Controller
{
    public function edit(AttendeePricingTierParser $parser): View
    {
        $platformTiers = AttendeePricingTier::query()
            ->where('scope_type', AttendeePricingTier::SCOPE_PLATFORM)
            ->orderBy('band_from')
            ->get();

        $plans = collect(config('plans.plans'))->map(function ($plan, $key) use ($parser) {
            $tiers = AttendeePricingTier::query()
                ->where('scope_type', AttendeePricingTier::SCOPE_PLAN)
                ->where('plan_key', $key)
                ->orderBy('band_from')
                ->get();

            return [
                'key' => $key,
                'name' => $plan['name'],
                'text' => $tiers->isNotEmpty() ? $parser->format($tiers) : '',
            ];
        })->values();

        return view('superadmin.attendee-pricing.edit', [
            'platformText' => $parser->format($platformTiers),
            'plans' => $plans,
        ]);
    }

    public function updatePlatform(Request $request, AttendeePricingTierParser $parser): RedirectResponse
    {
        $validated = $request->validate(['tiers' => ['required', 'string']]);
        $rows = $parser->parse($validated['tiers']);

        DB::transaction(function () use ($rows): void {
            AttendeePricingTier::where('scope_type', AttendeePricingTier::SCOPE_PLATFORM)->delete();
            foreach ($rows as $row) {
                AttendeePricingTier::create($row + ['scope_type' => AttendeePricingTier::SCOPE_PLATFORM]);
            }
        });

        return back()->with('success', 'Platform default pricing updated.');
    }

    public function updatePlan(Request $request, string $planKey, AttendeePricingTierParser $parser): RedirectResponse
    {
        abort_unless(is_array(config("plans.plans.{$planKey}")), 404);
        $validated = $request->validate(['tiers' => ['nullable', 'string']]);
        $text = trim($validated['tiers'] ?? '');
        $rows = $text === '' ? [] : $parser->parse($text);

        DB::transaction(function () use ($planKey, $rows): void {
            AttendeePricingTier::where('scope_type', AttendeePricingTier::SCOPE_PLAN)->where('plan_key', $planKey)->delete();
            foreach ($rows as $row) {
                AttendeePricingTier::create($row + ['scope_type' => AttendeePricingTier::SCOPE_PLAN, 'plan_key' => $planKey]);
            }
        });

        return back()->with('success', $rows === [] ? 'Plan override cleared; this plan now uses the platform default pricing.' : 'Plan pricing updated.');
    }
}
