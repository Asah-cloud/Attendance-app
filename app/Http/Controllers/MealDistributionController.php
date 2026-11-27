<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MealCollection;
use App\Models\MealCollectionAudit;
use App\Models\MealDistribution;
use App\Models\MealStation;
use App\Models\MealWasteLog;
use App\Notifications\Concerns\NotifiesPerChannel;
use App\Notifications\MealStockLow;
use App\Services\AuditApprovalService;
use App\Services\EventRegistrationResolver;
use App\Support\Search;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MealDistributionController extends Controller
{
    public function index(Event $event): View
    {
        $this->authorize('viewMeals', $event);

        $meals = $event->mealDistributions()
            ->withSum('collections', 'quantity')
            ->withCount('collections')
            ->with('stationAllocations')
            ->latest('opens_at')
            ->get();
        $stations = $event->mealStations()->orderBy('name')->get();
        $confirmedCount = $event->registrations()
            ->where('status', EventRegistration::STATUS_CONFIRMED)
            ->count();
        $checkedInCount = $event->attendances()->distinct()->count('participant_id');
        $stationStaff = $event->staff()->where('company_id', $event->company_id)->role(['audit_staff', 'usher'])->with('roles')->orderBy('name')->get();
        $stations->load('staff');
        if (request()->user()->isAuditStaff()) {
            $stations = $this->staffStations($event)->get();
            foreach ($meals as $meal) {
                $meal->awaiting_collection_count = $event->registrations()->where('status', EventRegistration::STATUS_CONFIRMED)->whereDoesntHave('mealCollections', fn ($q) => $q->where('meal_distribution_id', $meal->id))->count();
                $this->scopeMealTotals($meal, $event);
            }

            return view('audit.food', compact('event', 'meals', 'stations', 'confirmedCount', 'checkedInCount'));
        }

        return view('meals.index', compact('event', 'meals', 'stations', 'confirmedCount', 'checkedInCount', 'stationStaff'));
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageMeals', $event);
        $validated = $this->validateMeal($request);
        $meal = $event->mealDistributions()->create($validated);
        $this->syncEntitlements($meal, $request->string('entitlements')->toString());

        return back()->with('success', 'Food distribution created.');
    }

    public function update(Request $request, Event $event, MealDistribution $meal): RedirectResponse
    {
        $this->authorize('manageMeals', $event);
        $this->ensureMealBelongsToEvent($meal, $event);
        $validated = $this->validateMeal($request);
        $issued = $meal->collections()->sum('quantity');
        if ($validated['total_portions'] < $meal->stationAllocations()->sum('allocated_portions')) {
            return back()->withErrors(['total_portions' => 'Stock cannot be lower than existing sharing point allocations.']);
        }
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
        $this->authorize('manageMeals', $event);
        $this->ensureMealBelongsToEvent($meal, $event);
        abort_if($meal->collections()->exists(), 422, 'A distribution with collections cannot be deleted.');
        $meal->delete();

        return back()->with('success', 'Food distribution removed.');
    }

    public function updateStations(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageMeals', $event);
        $names = collect(preg_split('/\r\n|\r|\n/', $request->string('stations')->toString()))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->values();

        $request->validate(['stations' => ['nullable', 'string', 'max:10000']]);
        DB::transaction(function () use ($event, $names): void {
            $removed = $event->mealStations()->whereNotIn('name', $names)->get();
            foreach ($removed as $station) {
                if (MealCollection::where('meal_station_id', $station->id)->exists()
                    || $station->allocations()->exists() || $station->staff()->exists()) {
                    throw ValidationException::withMessages(['stations' => 'A sharing point with stock, assigned staff or collection history cannot be removed.']);
                }
                $station->delete();
            }
            foreach ($names as $name) {
                $event->mealStations()->firstOrCreate(['name' => $name]);
            }
        });

        return back()->with('success', 'Serving stations updated.');
    }

    public function updateStationAllocations(Request $request, Event $event, MealDistribution $meal): RedirectResponse
    {
        $this->authorize('manageMeals', $event);
        $this->ensureMealBelongsToEvent($meal, $event);

        $validated = $request->validate([
            'allocations' => ['nullable', 'array'],
            'allocations.*' => ['nullable', 'integer', 'min:0'],
        ]);
        $allocations = $validated['allocations'] ?? [];

        DB::transaction(function () use ($event, $meal, $allocations): void {
            $meal = MealDistribution::query()->lockForUpdate()->findOrFail($meal->id);
            $stationIds = $event->mealStations()->pluck('id');
            if (collect($allocations)->keys()->diff($stationIds)->isNotEmpty() || array_sum($allocations) > $meal->total_portions) {
                throw ValidationException::withMessages(['allocations' => 'Allocate only to this event\'s sharing points, within the total meal stock.']);
            }
            foreach ($stationIds as $stationId) {
                $issued = $meal->issuedPortionsAtStation($stationId);
                if ($issued > 0 && (! isset($allocations[$stationId]) || $allocations[$stationId] < $issued)) {
                    throw ValidationException::withMessages(['allocations' => 'A sharing point allocation cannot be lower than portions already served there.']);
                }
            }
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
        $this->authorize('viewMeals', $event);
        $this->ensureMealBelongsToEvent($meal, $event);
        $meal->loadSum('collections', 'quantity');
        $stations = $this->staffStations($event)->orderBy('name')->get();
        $this->scopeMealTotals($meal, $event);

        $matches = collect();
        if ($request->filled('q')) {
            $search = trim($request->string('q')->toString());
            $matches = $event->registrations()
                ->where('status', EventRegistration::STATUS_CONFIRMED)
                ->whereHas('participant', fn ($query) => Search::apply(
                    $query,
                    $search,
                    $request->user()->isAudit() ? ['name'] : ['name', 'email'],
                    $request->user()->isAudit() ? [] : ['phone'],
                ))
                ->with(['participant', 'mealCollections' => fn ($query) => $query->where('meal_distribution_id', $meal->id)])
                ->limit(10)
                ->get();
        }

        $recent = $meal->collections()->when(request()->user()->isAuditStaff(), fn ($q) => $q->whereIn('meal_station_id', $this->staffStations($event)->select('meal_stations.id')))->with(['participant', 'issuer', 'station'])->latest('collected_at')->limit(10)->get();

        return view('meals.scanner', compact('event', 'meal', 'matches', 'recent', 'stations'));
    }

    public function status(Request $request, Event $event, MealDistribution $meal): JsonResponse
    {
        $this->authorize('viewMeals', $event);
        $this->ensureMealBelongsToEvent($meal, $event);
        $meal->loadSum('collections', 'quantity');

        $this->scopeMealTotals($meal, $event);

        // Only a sharing point this person may serve at; anything else is ignored rather than leaked.
        $station = $request->filled('station')
            ? $this->staffStations($event)->whereKey($request->integer('station'))->first()
            : null;

        return response()->json([
            'remaining' => $meal->remainingPortions(),
            'issued' => $meal->issuedPortions(),
            'total' => $meal->total_portions,
            'low_stock' => $meal->isLowStock(),
            'is_open' => $meal->isOpen(),
            'station' => $station ? [
                'id' => $station->id,
                'name' => $station->name,
                'served' => $meal->issuedPortionsAtStation($station->id),
                'people' => $meal->collections()->where('meal_station_id', $station->id)->distinct()->count('event_registration_id'),
                'allocated' => $meal->allocatedPortionsFor($station->id),
                'remaining' => $meal->remainingPortionsAtStation($station->id),
            ] : null,
            // Portions this person issued for this meal (from the audit trail, so a later scan by someone else doesn't take them away).
            'mine' => MealCollectionAudit::where('meal_distribution_id', $meal->id)
                ->where('performed_by', $request->user()->id)
                ->whereIn('action', ['issued', 'override'])
                ->sum('quantity_change'),
        ]);
    }

    public function issue(Request $request, Event $event, MealDistribution $meal): JsonResponse|RedirectResponse
    {
        $this->authorize('viewMeals', $event);
        $this->ensureMealBelongsToEvent($meal, $event);

        if (in_array($event->status, ['closed', 'cancelled'], true)) {
            return $this->issueResponse($request, false, 'This event is closed — food can no longer be distributed.', 422, 'not_open');
        }

        $validated = $request->validate([
            'registration_code' => ['required', 'string', 'max:100'],
            'override' => ['nullable', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:500', 'required_if:override,1'],
            'approval_code' => ['nullable', 'string', 'max:100'],
            'meal_station_id' => [$this->servesAtStations($event) ? 'required' : 'nullable', Rule::exists('meal_stations', 'id')->where('event_id', $event->id)],
            'scanned_at' => ['nullable', 'date'],
        ]);

        $registration = app(EventRegistrationResolver::class)->fromScan($event, $validated['registration_code']);
        $registration?->load('participant');

        if (! $registration || $registration->status !== EventRegistration::STATUS_CONFIRMED) {
            return $this->issueResponse($request, false, 'This QR code does not belong to a confirmed attendee or staff member for this event.', 422);
        }

        $override = $request->boolean('override');
        if ($this->servesAtStations($event)) {
            abort_unless($this->staffStations($event)->whereKey($validated['meal_station_id'])->exists(), 403);
        }
        if ($override && ! $request->user()->can('manageMeals', $event)) {
            abort_unless($request->user()->isAuditStaff(), 403);
        }

        $collectedAt = isset($validated['scanned_at']) ? min(now(), Carbon::parse($validated['scanned_at'])) : now();

        $result = DB::transaction(function () use ($event, $meal, $registration, $request, $override, $validated, $collectedAt): array {
            $lockedMeal = MealDistribution::query()->lockForUpdate()->findOrFail($meal->id);
            $entitlement = $lockedMeal->entitlementFor($registration->participant->category);
            $collection = MealCollection::query()
                ->where('meal_distribution_id', $lockedMeal->id)
                ->where('event_registration_id', $registration->id)
                ->lockForUpdate()
                ->first();

            if ($collection && $collection->quantity >= $entitlement && ! $override) {
                return [false, "Entitlement reached ({$entitlement} portion(s)) — already collected at {$collection->collected_at->format('g:i A')}.", 409, 'already_collected', $collection->collected_at->format('g:i A')];
            }
            if (! $lockedMeal->isOpen() && ! $override) {
                return [false, 'This food distribution is not open right now.', 422, 'not_open'];
            }
            if ($lockedMeal->collections()->sum('quantity') >= $lockedMeal->total_portions && ! $override) {
                return [false, 'No portions remain for this distribution.', 422, 'out_of_stock'];
            }

            $stationId = $validated['meal_station_id'] ?? null;
            if ($stationId) {
                $allocated = $lockedMeal->allocatedPortionsFor((int) $stationId);
                if ($request->user()->isAuditStaff() && $allocated === null) {
                    throw ValidationException::withMessages(['meal_station_id' => 'Ask your Audit Head to allocate stock to this sharing point first.']);
                }
                if ($allocated !== null && $lockedMeal->issuedPortionsAtStation((int) $stationId) >= $allocated && ! $override) {
                    return [false, 'No portions remain allocated to this station.', 422, 'out_of_stock'];
                }
            }

            if ($collection && $request->user()->isAuditStaff() && $collection->meal_station_id != ($validated['meal_station_id'] ?? null)) {
                throw ValidationException::withMessages(['meal_station_id' => 'Additional portions must be issued at the original sharing point.']);
            }
            if ($override && ! $request->user()->can('manageMeals', $event)) {
                app(AuditApprovalService::class)->consume($request, $event, 'override', $meal->id, $registration->id);
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

            return [true, "Approved — {$registration->participant->name} received 1 portion.", 200, $override ? 'extra_portion' : 'served'];
        });

        return $this->issueResponse($request, $result[0], $result[1], $result[2], $result[3], $registration->participant->name, $result[4] ?? null);
    }

    public function reverse(Event $event, MealDistribution $meal, MealCollection $collection): RedirectResponse
    {
        $this->authorize('viewMeals', $event);
        abort_unless(request()->user()->can('manageMeals', $event) || request()->user()->isAuditStaff(), 403);
        request()->validate(['reason' => [request()->user()->isAudit() ? 'required' : 'nullable', 'string', 'max:500']]);
        if (request()->user()->isAuditStaff()) {
            abort_unless($this->staffStations($event)->whereKey($collection->meal_station_id)->exists(), 403);
        }
        $this->ensureMealBelongsToEvent($meal, $event);
        abort_unless($collection->meal_distribution_id === $meal->id, 404);
        DB::transaction(function () use ($event, $meal, $collection): void {
            $locked = MealCollection::query()->lockForUpdate()->findOrFail($collection->id);
            if (! request()->user()->can('manageMeals', $event)) {
                app(AuditApprovalService::class)->consume(request(), $event, 'reverse', $meal->id, $collection->id);
            }
            MealCollectionAudit::create([
                'event_id' => $event->id,
                'meal_distribution_id' => $meal->id,
                'event_registration_id' => $locked->event_registration_id,
                'participant_id' => $locked->participant_id,
                'performed_by' => request()->user()->id,
                'action' => 'reversed',
                'quantity_change' => -1,
                'reason' => request('reason') ?: 'Portion reversed by a manager.',
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
        $this->authorize('manageMeals', $event);
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
        $this->authorize('manageMeals', $event);
        $event->loadMissing('company');
        $registrations = $event->registrations()
            ->where('status', EventRegistration::STATUS_CONFIRMED)

            ->with('participant')
            ->get();
        $meals = $event->mealDistributions()->with('entitlements')->orderBy('opens_at')->get();

        return view('meals.vouchers', compact('event', 'registrations', 'meals'));
    }

    /** Meal types in serving order, with default serving windows. */
    public const TICKET_MEALS = [
        'breakfast' => ['label' => 'Breakfast', 'from' => '07:00', 'to' => '09:00'],
        'lunch' => ['label' => 'Lunch', 'from' => '12:30', 'to' => '14:00'],
        'supper' => ['label' => 'Supper', 'from' => '18:00', 'to' => '20:00'],
    ];

    private const TICKET_MAX_DAYS = 31;

    private const TICKET_MAX_SETS = 2000;

    /** Printable area of an A4 page inside the 8mm print margins, in mm (height kept a little short so rounding never spills a page). */
    private const TICKET_PAGE_WIDTH = 194;

    private const TICKET_PAGE_HEIGHT = 279;

    /**
     * Printable paper meal tickets: one set per attendee (named, or a chosen
     * number of unnamed sets) with a tear-off ticket for every meal between
     * the chosen first and last meal. Each set is a day-by-meal block sized
     * so a whole number of sets fills each A4 page, for easy cutting.
     */
    public function tickets(Request $request, Event $event): View
    {
        $this->authorize('manageMeals', $event);

        $mealKeys = array_keys(self::TICKET_MEALS);
        $eventStart = Carbon::parse($event->event_date)->toDateString();
        $eventEnd = Carbon::parse($event->end_date ?? $event->event_date)->toDateString();

        $settings = [
            'start_date' => $request->query('start_date', $eventStart),
            'start_meal' => $request->query('start_meal', 'breakfast'),
            'end_date' => $request->query('end_date', $eventEnd),
            'end_meal' => $request->query('end_meal', 'supper'),
            'meals' => (array) $request->query('meals', $mealKeys),
            'show_names' => $request->query('show_names', '0'),
            'sets' => $request->query('sets'),
        ];
        foreach (self::TICKET_MEALS as $key => $meal) {
            $settings["{$key}_from"] = $request->query("{$key}_from", $meal['from']);
            $settings["{$key}_to"] = $request->query("{$key}_to", $meal['to']);
        }

        $timeRules = [];
        foreach ($mealKeys as $key) {
            $timeRules["{$key}_from"] = ['nullable', 'date_format:H:i'];
            $timeRules["{$key}_to"] = ['nullable', 'date_format:H:i'];
        }
        $validator = validator($settings, [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'start_meal' => ['required', Rule::in($mealKeys)],
            'end_meal' => ['required', Rule::in($mealKeys)],
            'meals' => ['required', 'array', 'min:1'],
            'meals.*' => [Rule::in($mealKeys)],
            'show_names' => ['required', Rule::in(['0', '1'])],
            'sets' => ['nullable', 'integer', 'min:1', 'max:'.self::TICKET_MAX_SETS],
        ] + $timeRules, [
            'end_date.after_or_equal' => 'The last day must be on or after the first day.',
            'meals.required' => 'Tick at least one meal.',
            'sets.integer' => 'The number of sets must be a whole number.',
            'sets.min' => 'Print at least 1 set.',
            'sets.max' => 'You can print at most '.self::TICKET_MAX_SETS.' sets at a time.',
        ]);
        $validator->after(function ($validator) use ($settings, $mealKeys): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $start = Carbon::parse($settings['start_date']);
            $end = Carbon::parse($settings['end_date']);
            if ($start->diffInDays($end) + 1 > self::TICKET_MAX_DAYS) {
                $validator->errors()->add('end_date', 'Meal tickets can cover at most '.self::TICKET_MAX_DAYS.' days.');
            }
            if ($start->equalTo($end) && array_search($settings['end_meal'], $mealKeys) < array_search($settings['start_meal'], $mealKeys)) {
                $validator->errors()->add('end_meal', 'On a one-day programme the last meal cannot come before the first meal.');
            }
        });

        $registrations = $event->registrations()
            ->where('status', EventRegistration::STATUS_CONFIRMED)
            ->with('participant')
            ->get()
            ->sortBy(fn ($registration) => mb_strtolower($registration->participant->name))
            ->values();
        $showNames = $settings['show_names'] === '1';
        $settings['sets'] = $settings['sets'] ?? (string) max(1, $registrations->count());

        $slots = collect();
        $sets = collect();
        $layout = null;
        if (! $validator->fails()) {
            $startIndex = array_search($settings['start_meal'], $mealKeys);
            $endIndex = array_search($settings['end_meal'], $mealKeys);
            $date = Carbon::parse($settings['start_date']);
            $last = Carbon::parse($settings['end_date']);
            while ($date->lte($last)) {
                foreach ($mealKeys as $index => $key) {
                    if (! in_array($key, $settings['meals'], true)
                        || ($date->toDateString() === $settings['start_date'] && $index < $startIndex)
                        || ($date->toDateString() === $settings['end_date'] && $index > $endIndex)) {
                        continue;
                    }
                    $slots->push([
                        'label' => self::TICKET_MEALS[$key]['label'],
                        'date' => $date->copy(),
                        'from' => $settings["{$key}_from"] ? Carbon::createFromFormat('H:i', $settings["{$key}_from"]) : null,
                        'to' => $settings["{$key}_to"] ? Carbon::createFromFormat('H:i', $settings["{$key}_to"]) : null,
                    ]);
                }
                $date->addDay();
            }

            $sets = $showNames
                ? $registrations->map(fn ($registration) => [
                    'name' => $registration->participant->name,
                    'category' => $registration->participant->category ?: 'Attendee',
                ])
                : collect(range(1, (int) $settings['sets']))->map(fn ($number) => ['name' => null, 'category' => null]);

            $layout = $slots->isEmpty() ? null : $this->ticketLayout($slots->count(), $showNames);
        }
        if (! $validator->fails() && $slots->isEmpty()) {
            $validator->errors()->add('meals', 'No meals fall between the first and last meal you chose.');
        }

        return view('meals.tickets', [
            'event' => $event,
            'settings' => $settings,
            'mealTypes' => self::TICKET_MEALS,
            'slots' => $slots,
            // Tickets fill each set in serving order; any spare cells (only when the count cannot be split evenly) come last.
            'cells' => $layout ? $slots->pad($layout['columns'] * $layout['rows'], null) : collect(),
            'sets' => $sets,
            'layout' => $layout,
            'showNames' => $showNames,
            'registrations' => $registrations,
            'problems' => $validator->errors(),
        ]);
    }

    /**
     * Size each ticket set so a whole number of sets fills an A4 page.
     * Tickets fill a set in serving order, in the column count (3, 4 or 2)
     * that leaves the fewest empty cells; two-column sets sit two across.
     *
     * @return array{columns: int, rows: int, across: int, down: int, perPage: int, headerHeight: float, minSetHeight: float, spansPages: bool}
     */
    private function ticketLayout(int $tickets, bool $showNames): array
    {
        $candidates = array_values(array_filter([3, 4, 2], fn ($columns) => $columns <= $tickets)) ?: [$tickets];
        $columns = collect($candidates)->sortBy(fn ($columns) => (int) (ceil($tickets / $columns) * $columns) - $tickets)->first();
        $rows = (int) ceil($tickets / $columns);
        $across = intdiv(4, $columns) ?: 1;

        $headerHeight = $showNames ? 7.0 : 0.0;
        $minTicket = $showNames ? 26.0 : 22.0;
        $minSetHeight = $headerHeight + $rows * $minTicket;
        $spansPages = $minSetHeight > self::TICKET_PAGE_HEIGHT;
        $down = $spansPages ? 1 : (int) min(8, max(1, floor(self::TICKET_PAGE_HEIGHT / $minSetHeight)));

        return [
            'columns' => $columns,
            'rows' => $rows,
            'across' => $across,
            'down' => $down,
            'perPage' => $across * $down,
            'headerHeight' => $headerHeight,
            'minSetHeight' => $minSetHeight,
            'spansPages' => $spansPages,
        ];
    }

    public function report(Event $event): View
    {
        $this->authorize('viewMeals', $event);
        if (request()->user()->isAuditStaff()) {
            $collections = MealCollection::whereHas('distribution', fn ($q) => $q->where('event_id', $event->id))
                ->whereIn('meal_station_id', $this->staffStations($event)->select('meal_stations.id'))
                ->with(['participant', 'distribution', 'station'])->latest('collected_at')->paginate(30);

            return view('audit.report', compact('event', 'collections'));
        }
        $this->authorize('manageMeals', $event);

        return view('meals.report', $this->reportData($event));
    }

    public function exportCsv(Event $event)
    {
        $this->authorize('manageMeals', $event);
        $collections = $this->reportData($event)['collections'];

        return response()->streamDownload(function () use ($collections): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Distribution', 'Attendee', 'Category', 'Dietary notes', 'Email', 'Phone', 'Station', 'Portions', 'Override', 'Override reason', 'Issued by', 'Last served']);
            foreach ($collections as $collection) {
                fputcsv($output, [$collection->distribution->name, $collection->participant->name, $collection->participant->category, $collection->participant->dietary_notes, request()->user()->isAudit() ? '' : $collection->participant->email, request()->user()->isAudit() ? '' : $collection->participant->phone, $collection->station?->name, $collection->quantity, $collection->was_overridden ? 'Yes' : 'No', $collection->override_reason, $collection->issuer?->name, $collection->collected_at?->format('Y-m-d H:i:s')]);
            }
            fclose($output);
        }, 'food-report-'.str($event->title)->slug().'.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportPdf(Event $event)
    {
        $this->authorize('manageMeals', $event);

        return Pdf::loadView('meals.report-pdf', $this->reportData($event))
            ->setPaper('a4', 'landscape')
            ->download('food-report-'.str($event->title)->slug().'.pdf');
    }

    private function reportData(Event $event): array
    {
        $meals = $event->mealDistributions()->with('entitlements')->withSum('collections', 'quantity')->withCount('collections')->orderBy('opens_at')->get();
        $collections = MealCollection::query()->whereHas('distribution', fn ($query) => $query->where('event_id', $event->id))
            ->with(['distribution', 'participant', 'registration', 'issuer', 'station'])->latest('collected_at')->get();
        $audits = MealCollectionAudit::query()->where('event_id', $event->id)
            ->with(['distribution', 'participant', 'performer'])->latest('occurred_at')->get();
        $wasteLogs = MealWasteLog::query()->whereHas('distribution', fn ($query) => $query->where('event_id', $event->id))
            ->with(['distribution', 'loggedBy'])->latest('occurred_at')->get();

        $eligibleRegistrations = $event->registrations()
            ->where('status', EventRegistration::STATUS_CONFIRMED)

            ->with('participant')
            ->get();
        $confirmedByCategory = $eligibleRegistrations->countBy(fn ($registration) => $registration->participant->category ?: 'Unspecified');
        $forecast = $meals->map(fn ($meal) => [
            'meal' => $meal,
            'suggested' => $suggested = $confirmedByCategory->sum(fn ($count, $category) => $count * $meal->entitlementFor($category === 'Unspecified' ? null : $category)),
            'actual' => $actual = (int) $meal->collections_sum_quantity,
            'accuracy' => $suggested > 0 ? round(($actual / $suggested) * 100) : ($actual > 0 ? null : 100),
        ]);

        $byStation = $collections->groupBy(fn ($collection) => $collection->station?->name ?? 'Unassigned')
            ->map(fn ($group) => $group->sum('quantity'))
            ->sortDesc();

        $servedParticipantIds = $collections->pluck('participant_id')->unique();
        $noShows = $eligibleRegistrations->reject(fn ($registration) => $servedParticipantIds->contains($registration->participant_id))->values();

        $wasteByReason = $wasteLogs->groupBy(fn ($log) => trim($log->reason) ?: 'Unspecified')
            ->map(fn ($group) => $group->sum('quantity'))
            ->sortDesc();

        $dietarySummary = $eligibleRegistrations
            ->map(fn ($registration) => trim((string) $registration->participant->dietary_notes))
            ->filter()
            ->countBy(fn ($notes) => $notes)
            ->sortDesc();

        $collectionsByHour = $collections
            ->filter(fn ($collection) => $collection->collected_at)
            ->groupBy(fn ($collection) => $collection->collected_at->format('H:00'))
            ->map(fn ($group) => $group->sum('quantity'))
            ->sortKeys();

        return [
            'event' => $event,
            'meals' => $meals,
            'collections' => $collections,
            'audits' => $audits,
            'wasteLogs' => $wasteLogs,
            'forecast' => $forecast,
            'byStation' => $byStation,
            'noShows' => $noShows,
            'wasteByReason' => $wasteByReason,
            'dietarySummary' => $dietarySummary,
            'collectionsByHour' => $collectionsByHour,
            'totalStock' => $meals->sum('total_portions'),
            'totalIssued' => $collections->sum('quantity'),
            'totalWasted' => $wasteLogs->sum('quantity'),
            'peopleServed' => $collections->pluck('participant_id')->unique()->count(),
            'overrideCount' => $audits->where('action', 'override')->count(),
            'reversalCount' => $audits->where('action', 'reversed')->count(),
        ];
    }

    public function assignStaff(Request $request, Event $event, MealStation $station): RedirectResponse
    {
        $this->authorize('manageMeals', $event);
        abort_unless($station->event_id === $event->id, 404);
        $data = $request->validate(['staff_ids' => ['nullable', 'array'], 'staff_ids.*' => ['integer', 'distinct']]);
        $ids = $data['staff_ids'] ?? [];
        $allowed = $event->staff()->where('company_id', $event->company_id)->role(['audit_staff', 'usher'])->whereIn('users.id', $ids)->pluck('users.id');
        abort_unless($allowed->count() === count($ids), 403);
        $station->staff()->sync($allowed);

        return back()->with('success', 'Sharing point staff updated.');
    }

    private function staffStations(Event $event)
    {
        return $event->mealStations()->when($this->servesAtStations($event),
            fn ($q) => $q->whereHas('staff', fn ($users) => $users->where('users.id', request()->user()->id)));
    }

    /**
     * Whether the person may only serve at the sharing points they're on: always for audit staff,
     * and for an usher once they've been put on a point at this event (until then, any point).
     */
    private function servesAtStations(Event $event): bool
    {
        $user = request()->user();
        if ($user->isAuditStaff()) {
            return true;
        }

        return $user->hasRole('usher') && ! $user->hasAnyRole(['admin', 'manager', 'audit_head'])
            && $user->mealStations()->where('event_id', $event->id)->exists();
    }

    private function scopeMealTotals(MealDistribution $meal, Event $event): void
    {
        if (! request()->user()->isAuditStaff()) {
            return;
        }
        $ids = $this->staffStations($event)->pluck('meal_stations.id');
        $meal->total_portions = (int) $meal->stationAllocations()->whereIn('meal_station_id', $ids)->sum('allocated_portions');
        $meal->collections_sum_quantity = (int) $meal->collections()->whereIn('meal_station_id', $ids)->sum('quantity');
        $meal->collections_count = $meal->collections()->whereIn('meal_station_id', $ids)->count();
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
        $recipients = $meal->event->company->users->filter(fn ($user) => $user->hasRole('manager')
            || ($user->hasRole('audit_head') && $user->events()->whereKey($meal->event_id)->exists()));
        foreach ($recipients as $manager) {
            NotifiesPerChannel::send($manager, new MealStockLow($meal));
        }
    }

    private function ensureMealBelongsToEvent(MealDistribution $meal, Event $event): void
    {
        abort_unless($meal->event_id === $event->id, 404);
    }

    /**
     * The scanner uses outcome, name and collected_at to show a full-screen result, play a sound and speak it.
     */
    private function issueResponse(Request $request, bool $successful, string $message, int $status, string $outcome = 'invalid', ?string $name = null, ?string $collectedAt = null): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'successful' => $successful,
                'message' => $message,
                'outcome' => $outcome,
                'name' => $name,
                'collected_at' => $collectedAt,
            ], $status);
        }

        return back()->with($successful ? 'success' : 'error', $message);
    }
}
