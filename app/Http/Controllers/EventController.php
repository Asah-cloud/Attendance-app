<?php

namespace App\Http\Controllers;

use App\Imports\UsersImport;
use App\Models\Company;
use App\Models\Event;
use App\Services\RegistrationLifecycleService;
// Added these for the import to work
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user();
        $companies = collect(); // Default empty collection for non-admins

        if ($user->hasRole('admin')) {
            // Super Admin sees all companies with their nested relationships
            $companies = Company::with(['events', 'users'])->get();
            $events = Event::orderBy('created_at', 'desc')->get();
        } elseif ($user->hasRole('manager')) {
            // Managers only see events belonging to their specific company
            $events = Event::where('company_id', $user->company_id)
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            // Ushers only see events they have been assigned to work.
            $events = $user->events()
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return view('events.index', compact('events', 'companies'));
    }

    /**
     * Handle the Excel Import
     */
    public function import(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        // 1. Validate the file
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048',
        ]);

        try {
            // 2. Run the import using your UsersImport class
            Excel::import(new UsersImport($event), $request->file('file'));

            return back()->with('success', 'Participants imported successfully!');
        } catch (\Throwable $exception) {
            Log::error('Participant import failed.', [
                'event_id' => $event->id,
                'exception' => $exception,
            ]);

            return back()->with('error', 'The import could not be completed. Check the file format and try again.');
        }
    }

    public function show(Event $event)
    {
        $this->authorize('view', $event);

        return redirect("/events/{$event->id}/attendance");
    }

    public function create(Request $request)
    {
        // Capture company_id if passed from the "+ Assign Event Sheet" link
        $user = $request->user();
        $company_id = $user->hasRole('admin')
            ? $request->query('company_id')
            : $user->company_id;

        if ($company_id && ! Company::query()->whereKey($company_id)->exists()) {
            abort(404);
        }

        return view('events.create', compact('company_id'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'event_date' => 'required|date|after_or_equal:today',
            'end_date' => 'nullable|date|after_or_equal:event_date',
            'description' => 'nullable|string',
            'day' => 'nullable|integer|min:1',
            'company_id' => ['nullable', Rule::exists('companies', 'id')],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'flyer' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        // Determine which company this event belongs to
        $companyId = $user->hasRole('admin') ? $request->company_id : $user->company_id;

        if (! $companyId) {
            return back()->withInput()->with('error', 'No company profile linked to your account workflow.');
        }

        $company = Company::findOrFail($companyId);

        if (! $company->is_active || ($company->subscription_ends_at && $company->subscription_ends_at->endOfDay()->isPast())) {
            return back()->withInput()->with('error', 'This company subscription is inactive or expired.');
        }

        if ($request->hasFile('logo')) {
            $validated['logo_path'] = $request->file('logo')->store('event-logos', 'public');
        }
        if ($request->hasFile('flyer')) {
            $validated['flyer_path'] = $request->file('flyer')->store('event-flyers', 'public');
        }
        unset($validated['logo'], $validated['flyer']);

        // Enforce active subscription limits
        $created = DB::transaction(function () use ($companyId, $validated) {
            $company = Company::query()->lockForUpdate()->findOrFail($companyId);

            if ($company->events()->count() >= $company->event_limit) {
                return false;
            }

            $validated['company_id'] = $companyId;
            Event::create($validated);

            return true;
        });

        if (! $created) {
            if (! empty($validated['logo_path'])) {
                Storage::disk('public')->delete($validated['logo_path']);
            }
            if (! empty($validated['flyer_path'])) {
                Storage::disk('public')->delete($validated['flyer_path']);
            }

            return back()->withInput()->with('error', "Cannot create event. {$company->name} has reached its event limit of {$company->event_limit}.");
        }

        return redirect('/events')->with('success', 'Event created!');
    }

    public function updateDay(Request $request, Event $event)
    {
        $this->authorize('update', $event);
        $start = Carbon::parse($event->event_date)->startOfDay();
        $end = $event->end_date ? Carbon::parse($event->end_date)->startOfDay() : $start;
        $maxDays = $start->diffInDays($end) + 1;

        $validated = $request->validate([
            'day' => "required|integer|min:1|max:{$maxDays}",
        ]);

        $event->update(['day' => $validated['day']]);

        return back()->with('success', "Switched to Day {$validated['day']} successfully.");
    }

    public function edit(Event $event)
    {
        $this->authorize('update', $event);

        return view('events.edit', compact('event'));
    }

    public function update(Request $request, Event $event, RegistrationLifecycleService $lifecycle)
    {
        $this->authorize('update', $event);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'event_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:event_date',
            'description' => 'nullable|string',
            'location' => 'nullable|string',
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'flyer' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_flyer' => ['nullable', 'boolean'],
        ]);

        $detailsChanged = $event->event_date?->toDateString() !== $validated['event_date']
            || $event->end_date?->toDateString() !== ($validated['end_date'] ?? null)
            || (string) $event->location !== (string) ($validated['location'] ?? null);

        if ($request->boolean('remove_logo') && $event->logo_path) {
            Storage::disk('public')->delete($event->logo_path);
            $event->logo_path = null;
        }

        if ($request->hasFile('logo')) {
            if ($event->logo_path) {
                Storage::disk('public')->delete($event->logo_path);
            }
            $event->logo_path = $request->file('logo')->store('event-logos', 'public');
        }

        if ($request->boolean('remove_flyer') && $event->flyer_path) {
            Storage::disk('public')->delete($event->flyer_path);
            $event->flyer_path = null;
        }

        if ($request->hasFile('flyer')) {
            if ($event->flyer_path) {
                Storage::disk('public')->delete($event->flyer_path);
            }
            $event->flyer_path = $request->file('flyer')->store('event-flyers', 'public');
        }

        unset($validated['logo'], $validated['remove_logo'], $validated['flyer'], $validated['remove_flyer']);
        $event->fill($validated)->save();

        if ($detailsChanged) {
            $event->registrations()->update(['reminder_sent_at' => null]);
            $lifecycle->eventChanged($event->fresh());
        }

        return redirect('/events')->with('success', 'Event updated successfully!');
    }

    public function cancel(Event $event, RegistrationLifecycleService $lifecycle)
    {
        $this->authorize('delete', $event);
        $lifecycle->cancelEvent($event);

        return redirect('/events')->with('success', 'Event cancelled and attendees notified.');
    }

    public function destroy(Event $event)
    {
        $this->authorize('delete', $event);
        $event->delete();

        return redirect('/events')->with('success', 'Event and its records deleted.');
    }
}
