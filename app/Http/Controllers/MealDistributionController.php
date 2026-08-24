<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MealCollection;
use App\Models\MealDistribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MealDistributionController extends Controller
{
    public function index(Event $event): View
    {
        $this->authorize('view', $event);

        $meals = $event->mealDistributions()
            ->withSum('collections', 'quantity')
            ->withCount('collections')
            ->latest('opens_at')
            ->get();

        return view('meals.index', compact('event', 'meals'));
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        $validated = $this->validateMeal($request);
        $event->mealDistributions()->create($validated);

        return back()->with('success', 'Food distribution created.');
    }

    public function update(Request $request, Event $event, MealDistribution $meal): RedirectResponse
    {
        $this->authorize('update', $event);
        $this->ensureMealBelongsToEvent($meal, $event);
        $meal->update($this->validateMeal($request));

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

    public function scanner(Request $request, Event $event, MealDistribution $meal): View
    {
        $this->authorize('scanAttendance', $event);
        $this->ensureMealBelongsToEvent($meal, $event);
        $meal->loadSum('collections', 'quantity');

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

        $recent = $meal->collections()->with(['participant', 'issuer'])->latest('collected_at')->limit(10)->get();

        return view('meals.scanner', compact('event', 'meal', 'matches', 'recent'));
    }

    public function issue(Request $request, Event $event, MealDistribution $meal): JsonResponse|RedirectResponse
    {
        $this->authorize('scanAttendance', $event);
        $this->ensureMealBelongsToEvent($meal, $event);
        $validated = $request->validate([
            'registration_code' => ['required', 'string', 'max:100'],
            'override' => ['nullable', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:500', 'required_if:override,1'],
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

        $result = DB::transaction(function () use ($meal, $registration, $request, $override): array {
            $lockedMeal = MealDistribution::query()->lockForUpdate()->findOrFail($meal->id);
            $collection = MealCollection::query()
                ->where('meal_distribution_id', $lockedMeal->id)
                ->where('event_registration_id', $registration->id)
                ->lockForUpdate()
                ->first();

            if ($collection && ! $override) {
                return [false, "Already collected at {$collection->collected_at->format('g:i A')}.", 409];
            }
            if (! $lockedMeal->isOpen() && ! $override) {
                return [false, 'This food distribution is not open right now.', 422];
            }
            if ($lockedMeal->collections()->sum('quantity') >= $lockedMeal->total_portions && ! $override) {
                return [false, 'No portions remain for this distribution.', 422];
            }

            if ($collection) {
                $collection->increment('quantity');
                $collection->update([
                    'was_overridden' => true,
                    'override_reason' => $request->string('override_reason')->toString(),
                    'issued_by' => $request->user()->id,
                    'collected_at' => now(),
                ]);
            } else {
                $lockedMeal->collections()->create([
                    'event_registration_id' => $registration->id,
                    'participant_id' => $registration->participant_id,
                    'issued_by' => $request->user()->id,
                    'quantity' => 1,
                    'was_overridden' => $override,
                    'override_reason' => $override ? $request->string('override_reason')->toString() : null,
                    'collected_at' => now(),
                ]);
            }

            return [true, "Approved — {$registration->participant->name} received 1 portion.", 200];
        });

        return $this->issueResponse($request, ...$result);
    }

    public function reverse(Event $event, MealDistribution $meal, MealCollection $collection): RedirectResponse
    {
        $this->authorize('update', $event);
        $this->ensureMealBelongsToEvent($meal, $event);
        abort_unless($collection->meal_distribution_id === $meal->id, 404);
        $collection->delete();

        return back()->with('success', 'Food collection reversed and stock restored.');
    }

    private function validateMeal(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'total_portions' => ['required', 'integer', 'min:1', 'max:1000000'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'is_active' => ['required', 'boolean'],
        ]);
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
