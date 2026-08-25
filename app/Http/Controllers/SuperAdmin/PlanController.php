<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AttendeePricingTier;
use App\Models\Company;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        $plans = Plan::query()->orderBy('sort_order')->orderBy('id')->get();

        return view('superadmin.pricing.plans.index', compact('plans'));
    }

    public function create(): View
    {
        return view('superadmin.pricing.plans.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['key'] = $this->uniqueKey(Str::slug($validated['name']));
        $validated['sort_order'] = $validated['sort_order'] ?? ((Plan::max('sort_order') ?? 0) + 1);

        Plan::create($validated);

        return redirect()->route('pricing.plans.index')->with('success', 'Plan created.');
    }

    public function edit(Plan $plan): View
    {
        return view('superadmin.pricing.plans.edit', compact('plan'));
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->validated($request));

        return redirect()->route('pricing.plans.index')->with('success', 'Plan updated.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        if (Company::withTrashed()->where('plan_key', $plan->key)->exists()) {
            return back()->with('error', "Can't delete {$plan->name} — one or more companies are still assigned to it. Move them to a different plan first.");
        }

        DB::transaction(function () use ($plan): void {
            AttendeePricingTier::where('scope_type', AttendeePricingTier::SCOPE_PLAN)->where('plan_key', $plan->key)->delete();
            $plan->delete();
        });

        return redirect()->route('pricing.plans.index')->with('success', 'Plan deleted.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'event_limit' => ['required', 'integer', 'min:1'],
            'participant_limit' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'features' => ['nullable', 'string'],
            'featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['price_minor'] = (int) round(((float) $validated['price']) * 100);
        unset($validated['price']);

        $validated['features'] = collect(preg_split('/\r\n|\r|\n/', trim($validated['features'] ?? '')))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
        $validated['featured'] = $request->boolean('featured');

        return $validated;
    }

    private function uniqueKey(string $base): string
    {
        $key = $base;
        $suffix = 1;
        while (Plan::where('key', $key)->exists()) {
            $key = $base.'-'.(++$suffix);
        }

        return $key;
    }
}
