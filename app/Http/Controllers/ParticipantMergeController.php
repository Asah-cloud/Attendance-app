<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Services\ParticipantMergeService;
use App\Services\ParticipantRosterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ParticipantMergeController extends Controller
{
    public function index(Request $request): View
    {
        $query = $request->string('q')->trim()->toString();
        $participants = collect();

        if ($query !== '') {
            $participants = Participant::query()
                ->where('company_id', $request->user()->company_id)
                ->where(function ($builder) use ($query) {
                    $builder->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%");
                })
                ->withCount(['registrations', 'attendances'])
                ->orderBy('name')
                ->limit(50)
                ->get();
        }

        return view('participants.duplicates', [
            'query' => $query,
            'participants' => $participants,
            'company' => $request->user()->company,
        ]);
    }

    public function compare(Request $request): View|RedirectResponse
    {
        $ids = collect($request->input('ids', []))->filter()->unique()->values();
        if ($ids->count() !== 2) {
            return back()->withErrors(['ids' => 'Select exactly two records to compare.']);
        }

        $participants = Participant::query()
            ->where('company_id', $request->user()->company_id)
            ->whereIn('id', $ids)
            ->withCount(['registrations', 'attendances'])
            ->get();

        if ($participants->count() !== 2) {
            return back()->withErrors(['ids' => 'Could not find both records in your company.']);
        }

        return view('participants.duplicates-compare', ['participants' => $participants]);
    }

    public function merge(Request $request, ParticipantMergeService $merger): RedirectResponse
    {
        $validated = $request->validate([
            'primary_id' => ['required', 'integer'],
            'all_ids' => ['required', 'array', 'size:2'],
            'all_ids.*' => ['integer'],
        ]);

        $ids = collect($validated['all_ids'])->unique()->values();
        $duplicateId = $ids->first(fn ($id) => $id !== (int) $validated['primary_id']);

        if ($ids->count() !== 2 || $duplicateId === null || ! $ids->contains((int) $validated['primary_id'])) {
            return back()->withErrors(['primary_id' => 'Select which of the two compared records should survive.']);
        }

        $primary = Participant::where('company_id', $request->user()->company_id)->findOrFail($validated['primary_id']);
        $duplicate = Participant::where('company_id', $request->user()->company_id)->findOrFail($duplicateId);

        $merger->merge($primary, $duplicate, $request->user());

        return redirect()->route('participants.duplicates.index')->with('success', "Merged into {$primary->name}.");
    }

    /** Wipe the manager's own company's participant roster (see ParticipantRosterService). */
    public function destroyAll(Request $request, ParticipantRosterService $roster): RedirectResponse
    {
        $company = $request->user()->company;
        abort_unless($company, 403);

        if (trim((string) $request->input('confirm_name')) !== $company->name) {
            throw ValidationException::withMessages(['confirm_name' => 'Type the exact company name to confirm.']);
        }

        ['removed' => $removed, 'kept' => $kept] = $roster->clearRosterWithoutAttendance($company);

        $message = "Deleted {$removed} participant".($removed === 1 ? '' : 's').'.';
        if ($kept > 0) {
            $message .= " Kept {$kept} with recorded attendance.";
        }

        return redirect()->route('participants.duplicates.index')->with('success', $message);
    }
}
