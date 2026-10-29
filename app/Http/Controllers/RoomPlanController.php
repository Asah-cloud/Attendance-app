<?php

namespace App\Http\Controllers;

use App\Imports\UsersImport;
use App\Models\AccommodationBlock;
use App\Models\AccommodationFloor;
use App\Models\AccommodationRoom;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\ParticipantListImporter;
use App\Services\RoomAllocationService;
use App\Support\RoomRange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Room-plan import: upload a participant list and place those people a set number to a room.
 * The list is first run as a rolled-back trial so the manager sees who matched, who will be
 * registered, and the proposed rooms before anything is saved. Spare beds stay free.
 */
class RoomPlanController extends Controller
{
    public function __construct(
        private readonly ParticipantListImporter $importer,
        private readonly RoomAllocationService $rooms,
    ) {}

    public function upload(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($event->accommodation_enabled, 404);

        $siteIds = $event->accommodationSites()->pluck('id');
        $options = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,pdf', 'max:10240'],
            'per_room' => ['required', 'integer', 'min:1', 'max:20'],
            'site_id' => ['nullable', 'integer', Rule::exists('accommodation_sites', 'id')->where('event_id', $event->id)],
            'block_id' => ['nullable', 'integer', Rule::exists('accommodation_blocks', 'id')->whereIn('accommodation_site_id', $siteIds)],
            'floor_id' => ['nullable', 'integer', Rule::exists('accommodation_floors', 'id')->whereIn('accommodation_block_id', AccommodationBlock::whereIn('accommodation_site_id', $siteIds)->pluck('id'))],
            'rooms' => ['nullable', 'string', 'max:1000'],
            'room_ids' => ['nullable', 'array'],
            'room_ids.*' => ['integer'],
        ]);
        $roomIds = $this->chosenRooms($event, $options);
        $file = $request->file('file');
        $path = $file->storeAs('room-plans', Str::uuid().'.'.strtolower($file->getClientOriginalExtension()), 'local');

        $review = [
            'path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'per_room' => (int) $options['per_room'],
            'site_id' => isset($options['site_id']) ? (int) $options['site_id'] : null,
            'block_id' => isset($options['block_id']) ? (int) $options['block_id'] : null,
            'floor_id' => isset($options['floor_id']) ? (int) $options['floor_id'] : null,
            'room_ids' => $roomIds,
        ];

        try {
            $request->session()->put($this->key($event), $review + $this->trial($event, $review, []));
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);

            return $this->failed($event, $exception);
        }

        return redirect()->route('events.accommodation.plan.show', $event);
    }

    public function show(Request $request, Event $event): View|RedirectResponse
    {
        $this->authorize('update', $event);
        $review = $request->session()->get($this->key($event));
        if (! $review) {
            return redirect()->route('events.accommodation.section', [$event, 'room-plan']);
        }

        return view('accommodation.plan', ['event' => $event, 'review' => $review, 'location' => $this->locationLabel($event, $review)]);
    }

    /** Re-run the preview with the manager's choices for rows that matched someone of another name. */
    public function refresh(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        $review = $request->session()->get($this->key($event));
        abort_unless($review && Storage::disk('local')->exists($review['path']), 404);
        $decisions = $this->decisions($request);

        $request->session()->put($this->key($event), array_merge($review, $this->trial($event, $review, $decisions), ['decisions' => $decisions]));

        return redirect()->route('events.accommodation.plan.show', $event);
    }

    public function confirm(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        $review = $request->session()->pull($this->key($event));
        if (! $review || ! Storage::disk('local')->exists($review['path'])) {
            return redirect()->route('events.accommodation.section', [$event, 'room-plan'])->with('error', 'That room plan has expired. Please upload the list again.');
        }

        $result = null;
        try {
            $import = $this->importer->run($event, $review['path'], false, $this->decisions($request), false,
                function (UsersImport $import) use ($event, $review, $request, &$result): void {
                    $plan = $this->plan($event, $import, $review);
                    $result = $this->rooms->commitPlan($event, $plan, $request->user()->id) + ['already' => $plan['alreadyRoomed']->count()];
                });
        } catch (\Throwable $exception) {
            return $this->failed($event, $exception);
        } finally {
            Storage::disk('local')->delete($review['path']);
        }

        $counts = collect($import->rowResults())->countBy('status');
        $message = "Room plan saved: {$result['assigned']} placed".($result['already'] ? ", {$result['already']} already had a room" : '')
            .($result['unallocated'] ? ", {$result['unallocated']} could not be placed" : '').'.';
        $registered = $counts->get('newly_registered', 0) + $counts->get('new_person', 0) + $counts->get('reconfirmed', 0);
        if ($registered) {
            $message .= " {$registered} ".Str::plural('person', $registered).' newly registered for this event.';
        }

        return redirect()->route('events.accommodation.index', $event)->with('success', $message);
    }

    public function cancel(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        if ($review = $request->session()->pull($this->key($event))) {
            Storage::disk('local')->delete($review['path']);
        }

        return redirect()->route('events.accommodation.section', [$event, 'room-plan'])->with('success', 'Room plan cancelled. Nothing was changed.');
    }

    /** What the list would do, worked out in a rolled-back trial and kept as plain data for the preview page. */
    private function trial(Event $event, array $review, array $decisions): array
    {
        $preview = null;
        $import = $this->importer->run($event, $review['path'], false, $decisions, true,
            function (UsersImport $import) use ($event, $review, &$preview): void {
                $plan = $this->plan($event, $import, $review);
                $person = fn (EventRegistration $registration) => [
                    'name' => $registration->participant->name,
                    'gender' => $registration->participant->gender,
                    'group' => $registration->participant->room_group,
                ];
                $preview = [
                    'rooms' => $plan['proposals']->groupBy(fn ($proposal) => $proposal['room']->id)
                        ->map(fn ($proposals) => [
                            'label' => $proposals->first()['room']->label(),
                            'capacity' => $proposals->first()['room']->capacity,
                            'before' => $proposals->first()['room']->active_assignments_count,
                            'people' => $proposals->map(fn ($proposal) => $person($proposal['registration']))->values()->all(),
                        ])->values()->all(),
                    'unallocated' => $plan['unallocated']->map(fn ($item) => $person($item['registration']) + ['reason' => $item['reason']])->all(),
                    'already' => $plan['alreadyRoomed']->map(fn ($registration) => $person($registration) + ['room' => $registration->roomAssignment->room->label()])->all(),
                    'free_beds' => $plan['freeBeds'],
                    'to_place' => $plan['toPlace'],
                ];
            });

        return ['rows' => $import->rowResults(), 'review_rows' => $import->reviewRows(), 'preview' => $preview];
    }

    /** Everyone the list put on this event needs a room; place them per the plan's settings and the list's Room column. */
    private function plan(Event $event, UsersImport $import, array $review): array
    {
        $rows = collect($import->rowResults())->filter(fn ($row) => $row['registration_id']);
        $ids = $rows->pluck('registration_id')->unique();
        EventRegistration::whereIn('id', $ids)->update(['accommodation_required' => true]);
        $registrations = EventRegistration::whereIn('id', $ids)->get();
        $requested = $rows->filter(fn ($row) => filled($row['room'] ?? null))->mapWithKeys(fn ($row) => [$row['registration_id'] => $row['room']])->all();

        return $this->rooms->planBatch($event, $registrations, $review['per_room'], [
            'site_id' => $review['site_id'],
            'block_id' => $review['block_id'],
            'floor_id' => $review['floor_id'] ?? null,
            'room_ids' => $review['room_ids'] ?? null,
        ], $requested);
    }

    /**
     * The rooms ticked or typed ("101-110, 115"), within the chosen site, building or floor;
     * null when none were picked, meaning every room there.
     *
     * @return list<int>|null
     */
    private function chosenRooms(Event $event, array $options): ?array
    {
        $typed = trim((string) ($options['rooms'] ?? ''));
        $ticked = array_map('intval', $options['room_ids'] ?? []);
        if ($typed === '' && ! $ticked) {
            return null;
        }

        $rooms = AccommodationRoom::query()->with('floor.block')
            ->whereHas('floor.block', fn ($query) => $query->whereIn('accommodation_site_id', $event->accommodationSites()->pluck('id')))
            ->get()
            ->filter(fn (AccommodationRoom $room) => (empty($options['site_id']) || $room->floor->block->accommodation_site_id === (int) $options['site_id'])
                && (empty($options['block_id']) || $room->floor->accommodation_block_id === (int) $options['block_id'])
                && (empty($options['floor_id']) || $room->accommodation_floor_id === (int) $options['floor_id']));

        ['ids' => $ids, 'unmatched' => $unmatched] = RoomRange::parse($rooms, $typed);
        if ($unmatched) {
            throw ValidationException::withMessages(['rooms' => 'No room in the chosen place matches: '.implode(', ', $unmatched).'.']);
        }
        $ids = array_values(array_unique([...$ids, ...array_intersect($ticked, $rooms->modelKeys())]));
        if (! $ids) {
            throw ValidationException::withMessages(['rooms' => 'None of the chosen rooms are in the chosen place.']);
        }

        return $ids;
    }

    private function decisions(Request $request): array
    {
        return $request->validate(['decisions' => ['nullable', 'array'], 'decisions.*' => ['in:update,new,skip']])['decisions'] ?? [];
    }

    private function locationLabel(Event $event, array $review): string
    {
        if ($review['room_ids'] ?? null) {
            $names = AccommodationRoom::whereIn('id', $review['room_ids'])->pluck('name')->sort(fn ($a, $b) => strnatcasecmp($a, $b))->values();

            return count($names).' chosen '.Str::plural('room', count($names)).' ('.($names->count() > 6 ? $names->first().' … '.$names->last() : $names->join(', ')).')';
        }
        if ($review['floor_id'] ?? null) {
            $floor = AccommodationFloor::with('block.site')->find($review['floor_id']);

            return $floor ? $floor->block->name.' · '.$floor->name.' ('.$floor->block->site->name.')' : 'All rooms';
        }
        if ($review['block_id']) {
            $block = $event->accommodationSites()->with('blocks')->get()->flatMap->blocks->firstWhere('id', $review['block_id']);

            return $block ? $block->name.' ('.$block->site->name.')' : 'All rooms';
        }

        return $review['site_id'] ? ($event->accommodationSites()->find($review['site_id'])?->name ?? 'All rooms') : 'All rooms';
    }

    private function failed(Event $event, \Throwable $exception): RedirectResponse
    {
        if ($exception instanceof ValidationException) {
            return redirect()->route('events.accommodation.section', [$event, 'room-plan'])->with('error', collect($exception->errors())->flatten()->first());
        }
        Log::error('Room plan import failed.', ['event_id' => $event->id, 'exception' => $exception]);

        return redirect()->route('events.accommodation.section', [$event, 'room-plan'])->with('error', 'The room plan could not be completed. Check the file format and try again.');
    }

    private function key(Event $event): string
    {
        return 'room-plan.'.$event->id;
    }
}
