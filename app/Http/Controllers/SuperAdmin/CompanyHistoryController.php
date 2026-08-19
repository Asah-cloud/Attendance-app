<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CompanyHistoryController extends Controller
{
    public function index(): View
    {
        $companies = Company::onlyTrashed()
            ->withCount(['events', 'participants', 'users'])
            ->orderByDesc('deleted_at')
            ->get();

        return view('superadmin.companies.history.index', compact('companies'));
    }

    public function show(Company $company): View
    {
        $company->loadCount(['events', 'participants', 'users']);
        $events = $company->events()->withCount(['registrations', 'attendances'])->orderByDesc('event_date')->get();
        $participants = $company->participants()->orderBy('name')->paginate(50);

        return view('superadmin.companies.history.show', compact('company', 'events', 'participants'));
    }

    public function restore(Company $company): RedirectResponse
    {
        $company->restore();

        return redirect()->route('companies.index')->with('success', "{$company->name} has been restored.");
    }

    public function destroy(Company $company): RedirectResponse
    {
        $companyName = $company->name;

        Participant::where('company_id', $company->id)->delete();
        User::where('company_id', $company->id)->delete();

        if ($company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
        }

        // Force-deleting cascades to the company's events, which in turn
        // cascades to attendances, registrations, staff, and registration
        // fields at the database level - see add_company_id_to_users_table
        // and the migrations that build on it for the cascade chain.
        $company->forceDelete();

        return redirect()->route('companies.history.index')->with('success', "{$companyName} has been permanently deleted.");
    }
}
