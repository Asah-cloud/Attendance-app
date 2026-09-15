<?php

namespace App\Http\Controllers;

use App\Imports\SupportStaffImport;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Services\ApplicationCache;
use App\Services\EventRegistrationResolver;
use App\Services\ParticipantRosterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class SupportStaffController extends Controller
{
    /** The day value recorded for a support-staff check-in - see AttendanceSearch::STAFF_CHECKIN_DAY. */
    private const CHECKIN_DAY = -1;

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

    /** Wipe a company's support-staff roster, keeping anyone with recorded attendance (see ParticipantRosterService). */
    public function destroyAll(Request $request, ParticipantRosterService $roster): RedirectResponse
    {
        $company = $request->user()->hasRole('admin')
            ? Company::find($request->integer('company_id'))
            : $request->user()->company;
        abort_unless($company, 403);

        if (trim((string) $request->input('confirm_name')) !== $company->name) {
            throw ValidationException::withMessages(['confirm_name' => 'Type the exact company name to confirm.']);
        }

        ['removed' => $removed, 'kept' => $kept] = $roster->clearSupportStaffWithoutAttendance($company);

        $message = "Deleted {$removed} staff member".($removed === 1 ? '' : 's').'.';
        if ($kept > 0) {
            $message .= " Kept {$kept} with recorded attendance.";
        }

        return redirect()->route('support-staff.index', $request->user()->hasRole('admin') ? ['company_id' => $company->id] : [])
            ->with('success', $message);
    }

    public function checkin(Event $event): View
    {
        $this->authorize('view', $event);

        return view('support-staff.checkin', compact('event'));
    }

    public function checkinScanner(Event $event): View
    {
        $this->authorize('scanAttendance', $event);

        return view('support-staff.scanner', compact('event'));
    }

    public function scan(Request $request, Event $event, EventRegistrationResolver $resolver): JsonResponse
    {
        $this->authorize('scanAttendance', $event);

        $validated = $request->validate(['registration_code' => ['required', 'string', 'max:500']]);

        $registration = $resolver->fromScan($event, $validated['registration_code']);
        $registration?->loadMissing('participant');

        if (! $registration || ! $registration->participant->is_support_staff) {
            return response()->json(['message' => 'This code does not belong to event staff for this event.'], 422);
        }

        if ($registration->status !== EventRegistration::STATUS_CONFIRMED) {
            return response()->json(['message' => "{$registration->participant->name} is not a confirmed staff member for this event."], 422);
        }

        if ($event->isClosed()) {
            return response()->json(['message' => 'This event is closed.'], 422);
        }

        $attendance = Attendance::query()->createOrFirst([
            'event_id' => $event->id,
            'participant_id' => $registration->participant_id,
            'day' => self::CHECKIN_DAY,
        ], [
            'status' => 'present',
            'marked_by' => Auth::id(),
        ]);

        app(ApplicationCache::class)->invalidateEvent($event->id, $event->company_id);

        $message = $attendance->wasRecentlyCreated
            ? "Welcome, {$registration->participant->name}! Badge check-in complete."
            : "{$registration->participant->name} is already checked in.";

        return response()->json(['successful' => true, 'message' => $message]);
    }

    public function report(Event $event): View
    {
        $this->authorize('view', $event);

        [$staff, $checkedIn, $notCheckedIn] = $this->reportData($event);

        return view('support-staff.report', [
            'event' => $event,
            'total' => $staff->count(),
            'checkedIn' => $checkedIn,
            'notCheckedIn' => $notCheckedIn,
        ]);
    }

    public function reportCsv(Event $event)
    {
        $this->authorize('view', $event);
        [, $checkedIn, $notCheckedIn] = $this->reportData($event);
        $filename = Str::slug($event->title).'-staff-checkin.csv';

        return response()->streamDownload(function () use ($checkedIn, $notCheckedIn): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Staff', 'Staff ID', 'Department', 'Category', 'Status', 'Checked in at']);
            foreach ($checkedIn as $person) {
                fputcsv($handle, [$person->name, $person->staff_code, $person->department, $person->category, 'Checked in', $person->attendances->first()?->created_at?->toDateTimeString()]);
            }
            foreach ($notCheckedIn as $person) {
                fputcsv($handle, [$person->name, $person->staff_code, $person->department, $person->category, 'Not checked in', '']);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection, 2: \Illuminate\Support\Collection} */
    private function reportData(Event $event): array
    {
        $staff = $event->confirmedStaff()
            ->with(['attendances' => fn ($query) => $query->where('event_id', $event->id)->where('day', self::CHECKIN_DAY)])
            ->orderBy('name')->get();

        $checkedIn = $staff->filter(fn ($person) => $person->attendances->isNotEmpty())->values();
        $notCheckedIn = $staff->diff($checkedIn)->values();

        return [$staff, $checkedIn, $notCheckedIn];
    }
}
