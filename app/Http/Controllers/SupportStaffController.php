<?php

namespace App\Http\Controllers;

use App\Imports\SupportStaffImport;
use App\Models\Company;
use App\Models\Event;
use App\Models\Participant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class SupportStaffController extends Controller
{
    public function index(Request $request): View
    {
        $companies = collect();
        if ($request->user()->hasRole('admin')) {
            $companies = Company::query()->orderBy('name')->get(['id', 'name']);
            $companyId = $request->integer('company_id') ?: $companies->first()?->id;
            abort_unless($companies->contains('id', $companyId), 404);
        } else {
            $companyId = $request->user()->company_id;
            abort_unless($companyId, 403);
        }

        $selectedCompany = Company::findOrFail($companyId);

        $staff = Participant::query()->where('company_id', $companyId)->where('is_support_staff', true)
            ->with(['registrations.event'])->orderBy('name')->paginate(30);
        $events = Event::query()->where('company_id', $companyId)->whereNull('cancelled_at')
            ->where(fn ($query) => $query
                ->whereDate('end_date', '>=', now()->toDateString())
                ->orWhere(fn ($singleDay) => $singleDay->whereNull('end_date')->whereDate('event_date', '>=', now()->toDateString())))
            ->orderBy('event_date')->get();

        return view('support-staff.index', compact('staff', 'events', 'companies', 'selectedCompany'));
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048'],
            'event_ids' => ['required', 'array', 'min:1'],
            'event_ids.*' => ['integer', 'distinct'],
            'company_id' => ['nullable', 'integer'],
        ]);
        $companyId = $request->user()->hasRole('admin')
            ? (int) ($data['company_id'] ?? 0)
            : (int) $request->user()->company_id;
        abort_unless($companyId && Company::whereKey($companyId)->exists(), 403);
        $eventIds = Event::query()->where('company_id', $companyId)->whereIn('id', $data['event_ids'])->pluck('id')->all();
        abort_unless(count($eventIds) === count($data['event_ids']), 403);

        $import = new SupportStaffImport($companyId, $eventIds);
        Excel::import($import, $request->file('file'));

        return redirect()->route('support-staff.index', $request->user()->hasRole('admin') ? ['company_id' => $companyId] : [])
            ->with('success', "Staff roster imported: {$import->created} created, {$import->updated} matched, {$import->assigned} new event assignment(s).");
    }
}
