<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AttendeePricingTier;
use App\Models\Company;
use App\Services\AttendeePricingTierParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::all();

        return view('superadmin.companies.index', compact('companies'));
    }

    public function create()
    {
        return view('superadmin.companies.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'subscription_ends_at' => 'required|date',
            'event_limit' => 'required|integer|min:1',
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo_path'] = $request->file('logo')->store('company-logos', 'public');
        }

        Company::create(collect($validated)->only(['name', 'email', 'subscription_ends_at', 'event_limit', 'logo_path'])->all());

        return redirect()->route('companies.index')->with('success', 'Company created successfully!');
    }

    /**
     * Show the form for editing the specified company.
     */
    public function edit(Company $company, AttendeePricingTierParser $parser)
    {
        $attendeeTiers = AttendeePricingTier::query()
            ->where('scope_type', AttendeePricingTier::SCOPE_COMPANY)
            ->where('company_id', $company->id)
            ->orderBy('band_from')
            ->get();
        $attendeeTiersText = $attendeeTiers->isNotEmpty() ? $parser->format($attendeeTiers) : '';

        return view('superadmin.companies.edit', compact('company', 'attendeeTiersText'));
    }

    /**
     * Update the specified company in storage.
     */
    public function update(Request $request, Company $company, AttendeePricingTierParser $parser)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'subscription_ends_at' => 'required|date',
            'event_limit' => 'required|integer|min:1',
            'is_active' => 'required|boolean',
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'sms_sender_status' => ['sometimes', 'in:unconfigured,pending,approved,rejected'],
            'attendee_tiers' => ['nullable', 'string'],
        ]);

        if ($request->boolean('remove_logo') && $company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
            $company->logo_path = null;
        }

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $company->logo_path = $request->file('logo')->store('company-logos', 'public');
        }

        $attendeeTiersText = trim($validated['attendee_tiers'] ?? '');
        $tierRows = $attendeeTiersText === '' ? [] : $parser->parse($attendeeTiersText);

        unset($validated['logo'], $validated['remove_logo'], $validated['attendee_tiers']);
        $company->fill($validated)->save();

        DB::transaction(function () use ($company, $tierRows): void {
            AttendeePricingTier::where('scope_type', AttendeePricingTier::SCOPE_COMPANY)->where('company_id', $company->id)->delete();
            foreach ($tierRows as $row) {
                AttendeePricingTier::create($row + ['scope_type' => AttendeePricingTier::SCOPE_COMPANY, 'company_id' => $company->id]);
            }
        });

        return redirect()->route('companies.index')->with('success', 'Company updated successfully!');
    }

    /**
     * Archive the specified company. Company has SoftDeletes, so this hides
     * it (and blocks its users via EnsureCompanyIsActive) without touching
     * its data - see CompanyHistoryController for restore/permanent delete.
     */
    public function destroy(Company $company)
    {
        $company->delete();

        return redirect()->route('companies.index')->with('success', "{$company->name} has been archived. Find it in History to restore or permanently delete it.");
    }
}
