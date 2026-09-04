<?php

namespace App\Http\Controllers;

use App\Models\AccommodationBlock;
use App\Models\AccommodationFloor;
use App\Models\AccommodationRoom;
use App\Models\AccommodationSite;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\RoomAssignment;
use App\Notifications\Concerns\NotifiesPerChannel;
use App\Notifications\RoomAssigned;
use App\Notifications\RoomSelectionInvite;
use App\Services\RoomAllocationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccommodationController extends Controller
{
    public function index(Event $event, RoomAllocationService $allocator)
    {
        $this->authorize('update', $event);
        $event->load(['accommodationSites.blocks.floors.rooms' => fn ($query) => $query->withCount('activeAssignments')]);
        $registrations = $event->registrations()->with(['participant', 'roomAssignment.room.floor.block.site'])
            ->where('status', EventRegistration::STATUS_CONFIRMED)
            ->orderByDesc('accommodation_required')->get();
        $preview = request()->boolean('preview') ? $allocator->preview($event) : null;
        $rooms = $event->accommodationSites->flatMap->blocks->flatMap->floors->flatMap->rooms;
        $requiredCount = $registrations->where('accommodation_required', true)->count();
        $assignedCount = $registrations->filter(fn ($r) => $r->roomAssignment && in_array($r->roomAssignment->status, ['assigned', 'checked_in'], true))->count();

        return view('accommodation.index', compact('event', 'registrations', 'preview', 'rooms', 'requiredCount', 'assignedCount'));
    }

    public function updateSettings(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        $data = $request->validate(['accommodation_self_select_closes_at' => ['nullable', 'date']]);
        $event->update([
            'accommodation_enabled' => $request->boolean('accommodation_enabled'),
            'accommodation_published' => $request->boolean('accommodation_published'),
            'accommodation_self_select_closes_at' => $data['accommodation_self_select_closes_at'] ?? null,
        ]);
        $message = 'Accommodation settings updated.';
        if ($event->accommodation_published) {
            $sent = $this->sendPendingNotifications($event);
            $message .= $sent > 0
                ? " Room notifications sent to {$sent} attendee(s)."
                : ' No rooms are assigned yet, so no attendees were notified.';
        }

        return back()->with('success', $message);
    }

    public function inviteSelfSelect(Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        if (! $event->accommodationSelfSelectOpen()) {
            return back()->with('error', 'Tick "Use accommodation", set a future "Let attendees pick until" time, and Save before sending the link.');
        }

        $recipients = fn () => $event->registrations()
            ->where('status', EventRegistration::STATUS_CONFIRMED)
            ->where('accommodation_required', true)
            ->whereDoesntHave('roomAssignment', fn ($query) => $query->whereIn('status', ['checked_in', 'checked_out']));

        if (! $recipients()->exists()) {
            return back()->with('error', 'No attendees are marked as needing a room. Tick "Needs room" for them, or use "Mark all confirmed attendees as needing a room".');
        }

        $count = 0;
        $recipients()->with('participant')->chunkById(100, function ($registrations) use (&$count): void {
            foreach ($registrations as $registration) {
                NotifiesPerChannel::send($registration->participant, new RoomSelectionInvite($registration));
                $count++;
            }
        });

        return back()->with('success', "Room-selection link sent to {$count} attendee(s).");
    }

    /** Let a manager see the self-select picker exactly as a given attendee would, even before self-select is open. Read-only. */
    public function previewRoomPicker(Event $event, EventRegistration $registration, RoomAllocationService $allocator): View
    {
        $this->authorize('update', $event);
        abort_unless($registration->event_id === $event->id, 404);
        $registration->load(['participant', 'event.company', 'roomAssignment.room.floor.block.site']);

        $rooms = $allocator->selectableRooms($registration)
            ->groupBy(fn ($room) => $room->floor->block->name)
            ->map(fn ($blockRooms) => $blockRooms->groupBy(fn ($room) => $room->floor->name));

        return view('registrations.room-select', ['registration' => $registration, 'rooms' => $rooms, 'preview' => true]);
    }

    public function markAllRequired(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        if (trim((string) $request->input('confirm_title')) !== $event->title) {
            return back()->with('error', 'Type the exact event title to confirm.');
        }

        $count = $event->registrations()
            ->where('status', EventRegistration::STATUS_CONFIRMED)
            ->where('accommodation_required', false)
            ->update(['accommodation_required' => true]);

        return back()->with('success', "{$count} attendee(s) marked as needing a room.");
    }

    public function storeSite(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        $data = $request->validate(['name' => ['required', 'string', 'max:255', Rule::unique('accommodation_sites')->where('event_id', $event->id)], 'address' => ['nullable', 'string'], 'check_in_instructions' => ['nullable', 'string']]);
        $event->accommodationSites()->create($data);

        return back()->with('success', 'Accommodation site created.');
    }

    public function storeBlock(Request $request, Event $event, AccommodationSite $site): RedirectResponse
    {
        $this->authorize('update', $event);
        $this->siteBelongs($site, $event);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'gender_restriction' => ['nullable', Rule::in(['Male', 'Female'])], 'category_restriction' => ['nullable', 'string', 'max:255'], 'priority' => ['nullable', 'integer', 'min:0']]);
        $site->blocks()->create($data);

        return back()->with('success', 'Block created.');
    }

    public function storeFloor(Request $request, Event $event, AccommodationBlock $block): RedirectResponse
    {
        $this->authorize('update', $event);
        $this->blockBelongs($block, $event);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'priority' => ['nullable', 'integer', 'min:0'], 'is_accessible' => ['nullable', 'boolean']]);
        $data['is_accessible'] = $request->boolean('is_accessible');
        $block->floors()->create($data);

        return back()->with('success', 'Floor created.');
    }

    public function storeRoom(Request $request, Event $event, AccommodationFloor $floor): RedirectResponse
    {
        $this->authorize('update', $event);
        $this->floorBelongs($floor, $event);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'capacity' => ['required', 'integer', 'min:1', 'max:1000'], 'gender_restriction' => ['nullable', Rule::in(['Male', 'Female'])], 'category_restriction' => ['nullable', 'string', 'max:255'], 'priority' => ['nullable', 'integer', 'min:0'], 'is_accessible' => ['nullable', 'boolean']]);
        $data['is_accessible'] = $request->boolean('is_accessible');
        $floor->rooms()->create($data);

        return back()->with('success', 'Room created.');
    }

    public function bulkStoreRooms(Request $request, Event $event, AccommodationFloor $floor): RedirectResponse
    {
        $this->authorize('update', $event);
        $this->floorBelongs($floor, $event);
        $data = $request->validate(['prefix' => ['required', 'string', 'max:50'], 'start' => ['required', 'integer', 'min:0', 'max:99999'], 'end' => ['required', 'integer', 'gte:start', 'max:99999'], 'capacity' => ['required', 'integer', 'min:1', 'max:1000'], 'gender_restriction' => ['nullable', Rule::in(['Male', 'Female'])], 'is_accessible' => ['nullable', 'boolean']]);
        abort_if(($data['end'] - $data['start']) > 500, 422, 'Create no more than 500 rooms at once.');
        $created = 0;
        for ($number = $data['start']; $number <= $data['end']; $number++) {
            $room = $floor->rooms()->firstOrCreate(['name' => $data['prefix'].$number], ['capacity' => $data['capacity'], 'gender_restriction' => $data['gender_restriction'] ?? null, 'is_accessible' => $request->boolean('is_accessible')]);
            $created += $room->wasRecentlyCreated ? 1 : 0;
        }

        return back()->with('success', "{$created} room(s) created.");
    }

    public function importRooms(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        abort_unless($handle, 422, 'The CSV could not be read.');
        $aliases = ['location' => 'site', 'building' => 'block'];
        $headers = array_map(function ($value) use ($aliases) {
            // Strip a leading UTF-8 BOM (Excel adds one) before matching the header name.
            $header = str(preg_replace('/^\xEF\xBB\xBF/', '', (string) $value))->trim()->lower()->replace(' ', '_')->toString();

            return $aliases[$header] ?? $header;
        }, fgetcsv($handle) ?: []);
        abort_if(array_diff(['site', 'block', 'floor', 'room', 'capacity'], $headers), 422, 'CSV columns must include location, building, floor, room and capacity.');
        $created = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                continue;
            }
            $item = array_combine($headers, $row);
            if (! trim($item['room'] ?? '') || ! filter_var($item['capacity'] ?? null, FILTER_VALIDATE_INT) || (int) $item['capacity'] < 1) {
                continue;
            }
            $site = $event->accommodationSites()->firstOrCreate(['name' => trim($item['site'])]);
            $block = $site->blocks()->firstOrCreate(['name' => trim($item['block'])]);
            $floor = $block->floors()->firstOrCreate(['name' => trim($item['floor'])], ['is_accessible' => filter_var($item['accessible'] ?? false, FILTER_VALIDATE_BOOL)]);
            $room = $floor->rooms()->firstOrCreate(['name' => trim($item['room'])], ['capacity' => (int) $item['capacity'], 'gender_restriction' => in_array($item['gender'] ?? null, ['Male', 'Female'], true) ? $item['gender'] : null, 'category_restriction' => trim($item['category'] ?? '') ?: null, 'is_accessible' => filter_var($item['accessible'] ?? false, FILTER_VALIDATE_BOOL)]);
            $created += $room->wasRecentlyCreated ? 1 : 0;
        }
        fclose($handle);

        return back()->with('success', "{$created} room(s) imported.");
    }

    public function updateRoom(Request $request, Event $event, AccommodationRoom $room): RedirectResponse
    {
        $this->authorize('update', $event);
        $this->roomBelongs($room, $event);
        $used = $room->activeAssignments()->count();
        $data = $request->validate(['name' => ['required', 'string', 'max:255', Rule::unique('accommodation_rooms')->where('accommodation_floor_id', $room->accommodation_floor_id)->ignore($room->id)], 'capacity' => ['required', 'integer', 'min:'.$used, 'max:1000'], 'status' => ['required', Rule::in(['active', 'reserved', 'closed'])], 'gender_restriction' => ['nullable', Rule::in(['Male', 'Female'])], 'category_restriction' => ['nullable', 'string', 'max:255'], 'is_accessible' => ['nullable', 'boolean'], 'priority' => ['nullable', 'integer', 'min:0']]);
        $data['is_accessible'] = $request->boolean('is_accessible');
        $room->update($data);

        return back()->with('success', 'Room updated.');
    }

    public function updateRequirement(Request $request, Event $event, EventRegistration $registration): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($registration->event_id === $event->id, 404);
        $data = $request->validate(['accommodation_required' => ['nullable', 'boolean'], 'accessibility_required' => ['nullable', 'boolean'], 'accommodation_notes' => ['nullable', 'string', 'max:2000']]);
        $data['accommodation_required'] = $request->boolean('accommodation_required');
        $data['accessibility_required'] = $data['accommodation_required'] && $request->boolean('accessibility_required');
        $registration->update($data);
        if (! $data['accommodation_required'] && $registration->roomAssignment?->status !== 'checked_in') {
            $registration->roomAssignment?->delete();
        }

        return back()->with('success', 'Accommodation requirement updated.');
    }

    public function allocate(Request $request, Event $event, RoomAllocationService $allocator): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($event->accommodation_enabled, 422, 'Tick "Use accommodation" and Save before assigning rooms.');
        $result = $allocator->commit($event, $request->user()->id);
        if ($event->accommodation_published) {
            $this->sendPendingNotifications($event);
        }

        return back()->with('success', "{$result['assigned']} attendee(s) allocated. {$result['unallocated']} remain unallocated.");
    }

    public function assign(Request $request, Event $event, EventRegistration $registration): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($registration->event_id === $event->id, 404);
        $data = $request->validate(['room_id' => ['required', 'integer'], 'is_locked' => ['nullable', 'boolean']]);
        $room = AccommodationRoom::with('floor.block.site')->findOrFail($data['room_id']);
        $this->roomBelongs($room, $event);
        $assignment = $registration->roomAssignment;
        abort_if($assignment?->status === 'checked_in' && $assignment->accommodation_room_id !== $room->id, 422, 'Checked-in attendees cannot be moved.');
        abort_if($room->status === 'closed', 422, 'That room is closed. Set it to active or reserved first.');
        abort_if(! $room->floor->is_active || ! $room->floor->block->is_active, 422, 'That room is on an inactive floor or block. Reactivate it first.');
        abort_if($room->activeAssignments()->where('event_registration_id', '!=', $registration->id)->count() >= $room->capacity, 422, 'That room is already full.');
        RoomAssignment::updateOrCreate(['event_registration_id' => $registration->id], ['accommodation_room_id' => $room->id, 'status' => 'assigned', 'method' => 'manual', 'is_locked' => $request->boolean('is_locked'), 'allocation_reason' => 'Manually assigned by a manager.', 'assigned_by' => $request->user()->id, 'assigned_at' => now()]);
        $registration->update(['accommodation_required' => true]);
        if ($event->accommodation_published) {
            $this->notifyAssignment($registration->roomAssignment()->firstOrFail());
        }

        return back()->with('success', 'Room assigned.');
    }

    public function destroyAssignment(Event $event, EventRegistration $registration): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($registration->event_id === $event->id, 404);
        abort_if($registration->roomAssignment?->status === 'checked_in', 422, 'Checked-in assignments cannot be removed.');
        $registration->roomAssignment?->delete();

        return back()->with('success', 'Room assignment removed.');
    }

    public function checkIn(Request $request, Event $event, EventRegistration $registration): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($registration->event_id === $event->id, 404);
        $assignment = $registration->roomAssignment()->firstOrFail();
        abort_if($assignment->status === 'checked_out', 422, 'This attendee has already checked out.');
        $assignment->update(['status' => 'checked_in', 'is_locked' => true, 'checked_in_at' => $assignment->checked_in_at ?? now(), 'checked_in_by' => $request->user()->id]);

        return back()->with('success', 'Accommodation check-in recorded.');
    }

    public function checkOut(Request $request, Event $event, EventRegistration $registration): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($registration->event_id === $event->id, 404);
        $assignment = $registration->roomAssignment()->firstOrFail();
        abort_unless($assignment->status === 'checked_in', 422, 'Check the attendee in before checking them out.');
        $assignment->update(['status' => 'checked_out', 'checked_out_at' => now(), 'checked_out_by' => $request->user()->id]);

        return back()->with('success', 'Accommodation check-out recorded.');
    }

    public function notify(Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($event->accommodation_published, 422, 'Tick "Show rooms to attendees" and Save before emailing rooms.');
        $sent = $this->sendPendingNotifications($event);

        return back()->with('success', "Room assignment notifications queued for {$sent} attendee(s).");
    }

    public function exportCsv(Event $event)
    {
        $this->authorize('update', $event);
        $assignments = $this->reportAssignments($event);

        return response()->streamDownload(function () use ($assignments): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Attendee', 'Gender', 'Category', 'Email', 'Phone', 'Location', 'Building', 'Floor', 'Room', 'Status', 'Method', 'Checked in', 'Checked out']);
            foreach ($assignments as $assignment) {
                $participant = $assignment->registration->participant;
                $room = $assignment->room;
                fputcsv($output, [$participant->name, $participant->gender, $participant->category, $participant->email, $participant->phone, $room->floor->block->site->name, $room->floor->block->name, $room->floor->name, $room->name, $assignment->status, $assignment->method, $assignment->checked_in_at?->format('Y-m-d H:i:s'), $assignment->checked_out_at?->format('Y-m-d H:i:s')]);
            }
            fclose($output);
        }, 'rooming-list-'.str($event->title)->slug().'.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportPdf(Event $event)
    {
        $this->authorize('update', $event);

        return Pdf::loadView('accommodation.report-pdf', ['event' => $event, 'assignments' => $this->reportAssignments($event)])
            ->setPaper('a4', 'landscape')->download('rooming-list-'.str($event->title)->slug().'.pdf');
    }

    public function updateSite(Request $request, Event $event, AccommodationSite $site): RedirectResponse
    {
        $this->authorize('update', $event);
        $this->siteBelongs($site, $event);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'address' => ['nullable', 'string'], 'check_in_instructions' => ['nullable', 'string'], 'is_active' => ['nullable', 'boolean']]);
        $data['is_active'] = $request->boolean('is_active');
        $site->update($data);

        return back()->with('success', 'Site updated.');
    }

    public function updateBlock(Request $request, Event $event, AccommodationBlock $block): RedirectResponse
    {
        $this->authorize('update', $event);
        $this->blockBelongs($block, $event);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'gender_restriction' => ['nullable', Rule::in(['Male', 'Female'])], 'category_restriction' => ['nullable', 'string'], 'priority' => ['required', 'integer', 'min:0'], 'is_active' => ['nullable', 'boolean']]);
        $data['is_active'] = $request->boolean('is_active');
        $block->update($data);

        return back()->with('success', 'Block updated.');
    }

    public function updateFloor(Request $request, Event $event, AccommodationFloor $floor): RedirectResponse
    {
        $this->authorize('update', $event);
        $this->floorBelongs($floor, $event);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'priority' => ['required', 'integer', 'min:0'], 'is_active' => ['nullable', 'boolean'], 'is_accessible' => ['nullable', 'boolean']]);
        $data['is_active'] = $request->boolean('is_active');
        $data['is_accessible'] = $request->boolean('is_accessible');
        $floor->update($data);

        return back()->with('success', 'Floor updated.');
    }

    public function destroyInventory(Event $event, string $type, int $id): RedirectResponse
    {
        $this->authorize('update', $event);
        $model = match ($type) {
            'site' => AccommodationSite::findOrFail($id),
            'block' => AccommodationBlock::findOrFail($id),
            'floor' => AccommodationFloor::findOrFail($id),
            'room' => AccommodationRoom::findOrFail($id),
            default => abort(404),
        };
        match ($type) {
            'site' => $this->siteBelongs($model, $event),
            'block' => $this->blockBelongs($model, $event),
            'floor' => $this->floorBelongs($model, $event),
            'room' => $this->roomBelongs($model, $event),
        };
        $column = match ($type) {
            'site' => 'accommodation_sites.id', 'block' => 'accommodation_blocks.id', 'floor' => 'accommodation_floors.id', default => null
        };
        $hasAssignments = $type === 'room'
            ? $model->assignments()->exists()
            : RoomAssignment::whereHas('room.floor.block.site', fn ($query) => $query->where($column, $id))->exists();
        abort_if($hasAssignments, 422, 'Inventory with assignment history cannot be deleted. Close it instead.');
        $model->delete();

        return back()->with('success', ucfirst($type).' deleted.');
    }

    private function siteBelongs(AccommodationSite $site, Event $event): void
    {
        abort_unless($site->event_id === $event->id, 404);
    }

    private function blockBelongs(AccommodationBlock $block, Event $event): void
    {
        $block->loadMissing('site');
        $this->siteBelongs($block->site, $event);
    }

    private function floorBelongs(AccommodationFloor $floor, Event $event): void
    {
        $floor->loadMissing('block.site');
        $this->siteBelongs($floor->block->site, $event);
    }

    private function roomBelongs(AccommodationRoom $room, Event $event): void
    {
        $room->loadMissing('floor.block.site');
        $this->siteBelongs($room->floor->block->site, $event);
    }

    private function reportAssignments(Event $event)
    {
        return RoomAssignment::query()
            ->whereHas('registration', fn ($query) => $query->where('event_id', $event->id))
            ->with(['registration.participant', 'room.floor.block.site'])
            ->get()->sortBy(fn ($assignment) => $assignment->room->label())->values();
    }

    private function sendPendingNotifications(Event $event): int
    {
        $assignments = RoomAssignment::query()
            ->whereNull('notification_sent_at')->whereIn('status', ['assigned', 'checked_in'])
            ->whereHas('registration', fn ($query) => $query->where('event_id', $event->id))
            ->with(['registration.participant', 'registration.event.company', 'room.floor.block.site'])->get();
        foreach ($assignments as $assignment) {
            $this->notifyAssignment($assignment);
        }

        return $assignments->count();
    }

    private function notifyAssignment(RoomAssignment $assignment): void
    {
        $assignment->loadMissing('registration.participant');
        NotifiesPerChannel::send($assignment->registration->participant, new RoomAssigned($assignment));
        $assignment->update(['notification_sent_at' => now()]);
    }
}
