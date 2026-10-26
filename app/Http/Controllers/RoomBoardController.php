<?php

namespace App\Http\Controllers;

use App\Models\AccommodationRoom;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\RoomAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** A drag-and-drop board of an event's rooms: move people between rooms or back to "needs a room". */
class RoomBoardController extends Controller
{
    public function show(Event $event): View
    {
        $this->authorize('update', $event);

        $sites = $event->accommodationSites()->where('is_active', true)->orderBy('name')
            ->with(['blocks' => fn ($q) => $q->where('is_active', true)->orderBy('priority')->orderBy('name'),
                'blocks.floors' => fn ($q) => $q->where('is_active', true)->orderBy('priority')->orderBy('name'),
                'blocks.floors.rooms' => fn ($q) => $q->where('status', '!=', AccommodationRoom::STATUS_CLOSED)->orderBy('priority')->orderBy('name'),
                'blocks.floors.rooms.activeAssignments.registration.participant'])
            ->get();

        $waiting = $event->registrations()->with('participant')
            ->where('status', EventRegistration::STATUS_CONFIRMED)
            ->where('accommodation_required', true)
            ->whereDoesntHave('roomAssignment', fn ($q) => $q->whereIn('status', ['assigned', 'checked_in']))
            ->get()->sortBy(fn ($registration) => $registration->participant->name, SORT_NATURAL | SORT_FLAG_CASE)->values();

        return view('accommodation.board', compact('event', 'sites', 'waiting'));
    }

    /**
     * Move one person to a room (or, with no room, back to "needs a room"). A room holding the
     * other gender refuses; an empty room kept for the other gender switches to this person's.
     */
    public function move(Request $request, Event $event, RoomAllocationService $allocator): JsonResponse
    {
        $this->authorize('update', $event);
        $data = $request->validate([
            'registration_id' => ['required', 'integer'],
            'room_id' => ['nullable', 'integer'],
        ]);
        $registration = $event->registrations()->with(['participant', 'roomAssignment'])->findOrFail($data['registration_id']);

        if (empty($data['room_id'])) {
            $result = $allocator->removeAssignment($registration);

            return response()->json($result, $result['ok'] ? 200 : 422);
        }

        $room = AccommodationRoom::with('floor.block.site')->findOrFail($data['room_id']);
        abort_unless($room->floor->block->site->event_id === $event->id, 404);

        $previousRoom = $registration->roomAssignment?->accommodation_room_id;
        $result = $allocator->assignManually($registration, $room, $request->user()->id, false, false, $event->accommodation_published);
        if ($result['ok'] && $previousRoom !== $room->id) {
            // A new room means any room email already sent is out of date: let "Email rooms to attendees" send it again.
            $registration->roomAssignment()->update(['notification_sent_at' => null]);
        }

        return response()->json($result + ['room' => $room->label()], $result['ok'] ? 200 : 422);
    }
}
