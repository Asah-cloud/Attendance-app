<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MealCollection;
use App\Models\MealCollectionAudit;
use App\Models\MealDistribution;
use App\Models\MealWasteLog;
use App\Notifications\Concerns\NotifiesPerChannel;
use App\Notifications\MealStockLow;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MealDistributionController extends Controller
{
    public function index(Event $event): View
    {
        $this->authorize('view', $event);

        $meals = $event->mealDistributions()
            ->withSum('collections', 'quantity')
            ->withCount('collections')
            ->with('stationAllocations')
            ->latest('opens_at')
            ->get();
        $stations = $event->mealStations()->orderBy('name')->get();
        $confirmedCount = $event->confirmedParticipants()->count();

        return view('meals.index', compact('event', 'meals', 'stations', 'confirmedCount'));
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        $validated = $this->validateMeal($request);
        $meal = $event->mealDistributions()->create($validated);
        $this->syncEntitlements($meal, $request->string('entitlements')->toString());

        return back()->with('success', 'Food distribution created.');
    }

    public function update(Request $request, Event $event, MealDistribution $meal): RedirectResponse
    {
        $this->authorize('update', $event);
        $this->ensureMealBelongsToEvent($meal, $event);
        $validated = $this->validateMeal($request);
        $issued = $meal->collections()->sum('quantity');
        if ($validated['total_portions'] < $issued) {
            return back()->withErrors(['total_portions' => "Stock cannot be lower than the {$issued} portions already issued."])->withInput();
        }
        if ($validated['total_portions'] > $meal->total_portions) {
            $validated['low_stock_notified_at'] = null;
        }
        $meal->update($validated);
        $this->syncEntitlements($meal, $request->string('entitlements')->toString());

        return back()->with('success', 'Food distribution updated.');
    }

    public function destroy(Event $event, MealDistribution $meal): RedirectResponse
    {
        $this->authorize('update', $event);
        $this->ensureMealBelongsToEvent($meal, $event);
        abort_if($meal->collections()->exists(), 422, 'A distribution with collections cannot be deleted.');
        $meal->delete();

        return back()->with('success', 'Food distribution removed.');
    }

    public function updateStations(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        $names = collect(preg_split('/\r\n|\r|\n/', $request->string('stations')->toString()))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->values();

        DB::transaction(function () use ($event, $names): void {
            $event->mealStations()->delete();
            foreach ($names as $name) {
                $event->mealStations()->create(['name' => $name]);
            }
        });

        return back()->with('success', 'Serving stations updated.');
    }

    public function updateStationAllocations(Request $request, Event $event, MealDistribution $meal): RedirectResponse
    {
        $this->authorize('update', $event);
        $this->ensureMealBelongsToEvent($meal, $event);

        $validated = $request->validate([
            'allocations' => ['nullable', 'array'],
            'allocations.*' => ['nullable', 'integer', 'min:0'],
        ]);
        $allocations = $validated['allocations'] ?? [];

        DB::transaction(function () use ($event, $meal, $allocations): void {
            foreach ($event->mealStations()->pluck('id') as $stationId) {
                $portions = $allocations[$stationId] ?? null;

                if ($portions === null || $portions === '') {
                    $meal->stationAllocations()->where('meal_station_id', $stationId)->delete();

                    continue;
                }

                $meal->stationAllocations()->updateOrCreate(
                    ['meal_station_id' => $stationId],
                    ['allocated_portions' => (int) $portions]
                );
            }
        });

        return back()->with('success', 'Station allocations updated.');
    }

    public function scanner(Request $request, Event $event, MealDistribution $meal): View
    {
        $this->authorize('scanAttendance', $event);
        $this->ensureMealBelongsToEvent($meal, $event);
        $meal->loadSum('collections', 'quantity');
        $stations = $event->mealStations()->orderBy('name')->get();

        $matches = collect();
        if ($request->filled('q')) {
            $search = trim($request->string('q')->toString());
            $matches = $event->registrations()
                ->where('status', EventRegistration::STATUS_CONFIRMED)
                ->whereHas('participant', fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"))
                ->with(['participant', 'mealCollections' => fn ($query) => $query->where('meal_distribution_id', $meal->id)])
                ->limit(10)
                ->get();
        }

        $recent = $meal->collections()->with(['participant', 'issuer', 'station'])->latest('collected_at')->limit(10)->get();

        return view('meals.scanner', compact('event', 'meal', 'matches', 'recent', 'stations'));
    }

    public function status(Event $event, MealDistribution $meal): JsonResponse
    {
        $this->authorize('scanAttendance', $event);
        $this->ensureMealBelongsToEvent($meal, $event);
        $meal->loadSum('collections', 'quantity');

        return response()->json([
            'remaining' => $meal->remainingPortions(),
            'issued' => $meal->issuedPortions(),
            'total' => $meal->total_portions,
            'low_stock' => $meal->isLowStock(),
            'is_open' => $meal->isOpen(),
        ]);
    }

    public function issue(Request $request, Event $event, MealDistribution $meal): JsonResponse|RedirectResponse
    {
        $this->authorize('scanAttendance', $event);
        $this->ensureMealBelongsToEvent($meal, $event);

        if (in_array($event->status, ['closed', 'cancelled'], true)) {
            return $this->issueResponse($request, false, 'This event is closed — food can no longer be distributed.', 422);
        }

        $validated = $request->validate([
            'registration_code' => ['required', 'string', 'max:100'],
            'override' => ['nullable', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:500', 'required_if:override,1'],
            'meal_station_id' => ['nullable', Rule::exists('meal_stations', 'id')->where('event_id', $event->id)],
            'scanned_at' => ['nullable', 'date'],
        ]);

        $code = str_starts_with($validated['registration_code'], 'ASAH-ATTENDANCE:')
            ? substr($validated['registration_code'], strlen('ASAH-ATTENDANCE:'))
            : $validated['registration_code'];
        $registration = $event->registrations()->with('participant')->where('registration_code', $code)->first();

        if (! $registration || $registration->status !== EventRegistration::STATUS_CONFIRMED) {
            return $this->issueResponse($request, false, 'This QR code does not belong to a confirmed attendee for this event.', 422);
        }

        $override = $request->boolean('override');
        if ($override && ! $request->user()->can('update', $event)) {
            abort(403);
        }

        $collectedAt = isset($validated['scanned_at']) ? min(now(), Carbon::parse($validated['scanned_at'])) : now();

        $result = DB::transaction(function () use ($meal, $registration, $request, $override, $validated, $collectedAt): array {
            $lockedMeal = MealDistribution::query()->lockForUpdate()->findOrFail($meal->id);
            $entitlement = $lockedMeal->entitlementFor($registration->participant->category);
            $collection = MealCollection::query()
                ->where('meal_distribution_id', $lockedMeal->id)
                ->where('event_registration_id', $registration->id)
                ->lockForUpdate()
                ->first();

            if ($collection && $collection->quantity >= $entitlement && ! $override) {
                return [false, "Entitlement reached ({$entitlement} portion(s)) — already collected at {$collection->collected_at->format('g:i A')}.", 409];
            }
            if (! $lockedMeal->isOpen() && ! $override) {
                return [false, 'This food distribution is not open right now.', 422];
            }
            if ($lockedMeal->collections()->sum('quantity') >= $lockedMeal->total_portions && ! $override) {
                return [false, 'No portions remain for this distribution.', 422];
            }

            $stationId = $validated['meal_station_id'] ?? null;
            if ($stationId) {
                $allocated = $lockedMeal->allocatedPortionsFor((int) $stationId);
                if ($allocated !== null && $lockedMeal->issuedPortionsAtStation((int) $stationId) >= $allocated && ! $override) {
                    return [false, 'No portions remain allocated to this station.', 422];
                }
            }

            if ($collection) {
                $collection->increment('quantity');
                $collection->update([
                    'was_overridden' => $collection->was_overridden || $override,
                    'override_reason' => $override ? $request->string('override_reason')->toString() : $collection->override_reason,
                    'issued_by' => $request->user()->id,
                    'meal_station_id' => $validated['meal_station_id'] ?? $collection->meal_station_id,
                    'collected_at' => $collectedAt,
                ]);
            } else {
                $collection = $lockedMeal->collections()->create([
                    'event_registration_id' => $registration->id,
                    'participant_id' => $registration->participant_id,
                    'meal_station_id' => $validated['meal_station_id'] ?? null,
                    'issued_by' => $request->user()->id,
                    'quantity' => 1,
                    'was_overridden' => $override,
                    'override_reason' => $override ? $request->string('override_reason')->toString() : null,
                    'collected_at' => $collectedAt,
                ]);
            }

            MealCollectionAudit::create([
                'event_id' => $lockedMeal->event_id,
                'meal_distribution_id' => $lockedMeal->id,
                'event_registration_id' => $registration->id,
                'participant_id' => $registration->participant_id,
                'performed_by' => $request->user()->id,
                'action' => $override ? 'override' : 'issued',
                'quantity_change' => 1,
                'reason' => $override ? $request->string('override_reason')->toString() : null,
                'occurred_at' => now(),
            ]);

            $this->maybeAlertLowStock($lockedMeal->fresh());

            return [true, "Approved — {$registration->participant->name} received 1 portion.", 200];
        });

        return $this->issueResponse($request, ...$result);
    }

    public function reverse(Event $event, MealDistribution $meal, MealCollection $collection): RedirectResponse
    {
        $this->authorize('update', $event);
        $this->ensureMealBelongsToEvent($meal, $event);
        abort_unless($collection->meal_distribution_id === $meal->id, 404);
        DB::transaction(function () use ($event, $meal, $collection): void {
            $locked = MealCollection::query()->lockForUpdate()->findOrFail($collection->id);
            MealCollectionAudit::create([
                'event_id' => $event->id,
                'meal_distribution_id' => $meal->id,
                'event_registration_id' => $locked->event_registration_id,
                'participant_id' => $locked->participant_id,
                'performed_by' => request()->user()->id,
                'action' => 'reversed',
                'quantity_change' => -1,
                'reason' => 'Portion reversed by a manager.',
                'occurred_at' => now(),
            ]);
            if ($locked->quantity > 1) {
                $locked->decrement('quantity');
            } else {
                $locked->delete();
            }
        });

        return back()->with('success', 'One food portion was reversed and returned to stock.');
    }

    public function logWaste(Request $request, Event $event, MealDistribution $meal): RedirectResponse
    {
        $this->authorize('update', $event);
        $this->ensureMealBelongsToEvent($meal, $event);
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $meal->wasteLogs()->create($validated + ['logged_by' => $request->user()->id, 'occurred_at' => now()]);

        return back()->with('success', 'Waste logged.');
    }

    public function vouchers(Event $event): View
    {
        $this->authorize('update', $event);
        $event->loadMissing('company');
        $registrations = $event->registrations()
            ->where('status', EventRegistration::STATUS_CONFIRMED)
            ->with('participant')
            ->get();
        $meals = $event->mealDistributions()->with('entitlements')->orderBy('opens_at')->get();

        return view('meals.vouchers', compact('event', 'registrations', 'meals'));
    }

    public function report(Event $event): View
    {
        $this->authorize('update', $event);

        return view('meals.report', $this->reportData($event));
    }

    public function exportCsv(Event $event)
    {
        $this->authorize('update', $event);
        $collections = $this->reportData($event)['collections'];

        return response()->streamDownload(function () use ($collections): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Distribution', 'Attendee', 'Category', 'Dietary notes', 'Email', 'Phone', 'Station', 'Portions', 'Override', 'Override reason', 'Issued by', 'Last served']);
            foreach ($collections as $collection) {
                fputcsv($output, [$collection->distribution->name, $collection->participant->name, $collection->participant->category, $collection->participant->dietary_notes, $collection->participant->email, $collection->participant->phone, $collection->station?->name, $collection->quantity, $collection->was_overridden ? 'Yes' : 'No', $collection->override_reason, $collection->issuer?->name, $collection->collected_at?->format('Y-m-d H:i:s')]);
            }
            fclose($output);
        }, 'food-report-'.str($event->title)->slug().'.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportPdf(Event $event)
    {
        $this->authorize('update', $event);

        return Pdf::loadView('meals.report-pdf', $this->reportData($event))
            ->setPaper('a4', 'landscape')
            ->download('food-report-'.str($event->title)->slug().'.pdf');
    }

    private function reportData(Event $event): array
    {
        $meals = $event->mealDistributions()->with('entitlements')->withSum('collections', 'quantity')->withCount('collections')->orderBy('opens_at')->get();
        $collections = MealCollection::query()->whereHas('distribution', fn ($query) => $query->where('event_id', $event->id))
            ->with(['distribution', 'participant', 'issuer', 'station'])->latest('collected_at')->get();
        $audits = MealCollectionAudit::query()->where('event_id', $event->id)
            ->with(['distribution', 'participant', 'performer'])->latest('occurred_at')->get();
        $wasteLogs = MealWasteLog::query()->whereHas('distribution', fn ($query) => $query->where('event_id', $event->id))
            ->with(['distribution', 'loggedBy'])->latest('occurred_at')->get();

        $confirmedByCategory = $event->confirmedParticipants()->get()->countBy(fn ($participant) => $participant->category ?: 'Unspecified');
        $forecast = $meals->map(fn ($meal) => [
            'meal' => $meal,
            'suggested' => $confirmedByCategory->sum(fn ($count, $category) => $count * $meal->entitlementFor($category === 'Unspecified' ? null : $category)),
        ]);

        $byStation = $collections->groupBy(fn ($collection) => $collection->station?->name ?? 'Unassigned')
            ->map(fn ($group) => $group->sum('quantity'))
            ->sortDesc();

        return [
            'event' => $event,
            'meals' => $meals,
            'collections' => $collections,
            'audits' => $audits,
            'wasteLogs' => $wasteLogs,
            'forecast' => $forecast,
            'byStation' => $byStation,
            'totalStock' => $meals->sum('total_portions'),
            'totalIssued' => $collections->sum('quantity'),
            'totalWasted' => $wasteLogs->sum('quantity'),
            'peopleServed' => $collections->pluck('participant_id')->unique()->count(),
            'overrideCount' => $audits->where('action', 'override')->count(),
            'reversalCount' => $audits->where('action', 'reversed')->count(),
        ];
    }

    private function validateMeal(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'total_portions' => ['required', 'integer', 'min:1', 'max:1000000'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'is_active' => ['required', 'boolean'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function syncEntitlements(MealDistribution $meal, ?string $text): void
    {
        $lines = collect(preg_split('/\r\n|\r|\n/', $text ?? ''))
            ->map(fn ($line) => trim($line))
            ->filter();

        DB::transaction(function () use ($meal, $lines): void {
            $meal->entitlements()->delete();
            foreach ($lines as $line) {
                if (! str_contains($line, ':')) {
                    continue;
                }
                [$category, $portions] = explode(':', $line, 2);
                $category = trim($category);
                $portions = (int) trim($portions);
                if ($category === '' || $portions < 1) {
                    continue;
                }
                $meal->entitlements()->create(['category' => $category, 'portions_allowed' => $portions]);
            }
        });
    }

    private function maybeAlertLowStock(MealDistribution $meal): void
    {
        if (! $meal->isLowStock() || $meal->low_stock_notified_at) {
            return;
        }

        $meal->update(['low_stock_notified_at' => now()]);
        $meal->loadMissing('event.company.users');
        foreach ($meal->event->company->users->where('role', 'manager') as $manager) {
            NotifiesPerChannel::send($manager, new MealStockLow($meal));
        }
    }

    private function ensureMealBelongsToEvent(MealDistribution $meal, Event $event): void
    {
        abort_unless($meal->event_id === $event->id, 404);
    }

    private function issueResponse(Request $request, bool $successful, string $message, int $status): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['successful' => $successful, 'message' => $message], $status);
        }

        return back()->with($successful ? 'success' : 'error', $message);
    }
}
