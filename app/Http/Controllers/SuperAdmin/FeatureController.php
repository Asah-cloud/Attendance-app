<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\EventFeature;
use App\Models\Feature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FeatureController extends Controller
{
    public function index(): View
    {
        $features = Feature::query()->orderBy('sort_order')->orderBy('id')->get();

        return view('superadmin.pricing.features.index', compact('features'));
    }

    public function create(): View
    {
        return view('superadmin.pricing.features.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['key'] = $this->uniqueKey(Str::slug($validated['name'], '_'));
        $validated['sort_order'] = $validated['sort_order'] ?? ((Feature::max('sort_order') ?? 0) + 1);

        Feature::create($validated);

        return redirect()->route('pricing.features.index')->with('success', 'Feature created.');
    }

    public function edit(Feature $feature): View
    {
        return view('superadmin.pricing.features.edit', compact('feature'));
    }

    public function update(Request $request, Feature $feature): RedirectResponse
    {
        $feature->update($this->validated($request));

        return redirect()->route('pricing.features.index')->with('success', 'Feature updated.');
    }

    public function destroy(Feature $feature): RedirectResponse
    {
        if (EventFeature::where('feature_key', $feature->key)->exists()) {
            return back()->with('error', "Can't delete {$feature->name} — one or more events have already bought it. Deactivate it instead to stop new sales.");
        }

        $feature->delete();

        return redirect()->route('pricing.features.index')->with('success', 'Feature deleted.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cost' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['cost_minor'] = (int) round(((float) $validated['cost']) * 100);
        unset($validated['cost']);

        $validated['tier'] = Feature::TIER_ADVANCED;
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    private function uniqueKey(string $base): string
    {
        $key = $base;
        $suffix = 1;
        while (Feature::where('key', $key)->exists()) {
            $key = $base.'_'.(++$suffix);
        }

        return $key;
    }
}
