<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AttendeePricingTier;
use App\Models\Company;
use App\Models\Event;
use App\Services\AttendeePricingTierParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompanyPricingController extends Controller
{
    public function index(Request $request): View
    {
        $companies = Company::query()
            ->when($request->string('search')->trim()->value(), fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.pricing.companies.index', compact('companies'));
    }

    public function show(Company $company, AttendeePricingTierParser $parser): View
    {
        $companyTiers = AttendeePricingTier::query()
            ->where('scope_type', AttendeePricingTier::SCOPE_COMPANY)
            ->where('company_id', $company->id)
            ->orderBy('band_from')
            ->get();
        $companyTiersText = $companyTiers->isNotEmpty() ? $parser->format($companyTiers) : '';

        $events = $company->events()->orderByDesc('event_date')->get();
        $eventsWithOverride = AttendeePricingTier::query()
            ->where('scope_type', AttendeePricingTier::SCOPE_EVENT)
            ->whereIn('event_id', $events->pluck('id'))
            ->pluck('event_id')
            ->all();

        return view('superadmin.pricing.companies.show', compact('company', 'companyTiersText', 'events', 'eventsWithOverride'));
    }

    public function update(Request $request, Company $company, AttendeePricingTierParser $parser): RedirectResponse
    {
        $validated = $request->validate(['tiers' => ['nullable', 'string']]);
        $text = trim($validated['tiers'] ?? '');
        $rows = $text === '' ? [] : $parser->parse($text);

        DB::transaction(function () use ($company, $rows): void {
            AttendeePricingTier::where('scope_type', AttendeePricingTier::SCOPE_COMPANY)->where('company_id', $company->id)->delete();
            foreach ($rows as $row) {
                AttendeePricingTier::create($row + ['scope_type' => AttendeePricingTier::SCOPE_COMPANY, 'company_id' => $company->id]);
            }
        });

        return back()->with('success', $rows === [] ? 'Company override cleared; falls back to plan/platform pricing.' : 'Company pricing updated.');
    }

    public function editEvent(Company $company, Event $event, AttendeePricingTierParser $parser): View
    {
        abort_unless($event->company_id === $company->id, 404);

        $tiers = AttendeePricingTier::query()
            ->where('scope_type', AttendeePricingTier::SCOPE_EVENT)
            ->where('event_id', $event->id)
            ->orderBy('band_from')
            ->get();

        return view('superadmin.pricing.companies.event-edit', [
            'company' => $company,
            'event' => $event,
            'text' => $tiers->isNotEmpty() ? $parser->format($tiers) : '',
        ]);
    }

    public function updateEvent(Request $request, Company $company, Event $event, AttendeePricingTierParser $parser): RedirectResponse
    {
        abort_unless($event->company_id === $company->id, 404);

        $validated = $request->validate(['tiers' => ['nullable', 'string']]);
        $text = trim($validated['tiers'] ?? '');
        $rows = $text === '' ? [] : $parser->parse($text);

        DB::transaction(function () use ($event, $rows): void {
            AttendeePricingTier::where('scope_type', AttendeePricingTier::SCOPE_EVENT)->where('event_id', $event->id)->delete();
            foreach ($rows as $row) {
                AttendeePricingTier::create($row + ['scope_type' => AttendeePricingTier::SCOPE_EVENT, 'event_id' => $event->id]);
            }
        });

        return redirect()->route('pricing.companies.show', $company)
            ->with('success', $rows === [] ? 'Event override cleared; falls back to company/plan/platform pricing.' : 'Event pricing updated.');
    }
}
