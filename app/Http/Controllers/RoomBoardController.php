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
     * Move one person to a room (or, with no room, back to "needs a room"). A move that would put
     * someone in a room for the other gender, or alongside the other gender, needs confirming.
     */
    public function move(Request $request, Event $event, RoomAllocationService $allocator): JsonResponse
    {
        $this->authorize('update', $event);
        $data = $request->validate([
            'registration_id' => ['required', 'integer'],
            'room_id' => ['nullable', 'integer'],
            'confirmed' => ['nullable', 'boolean'],
        ]);
        $registration = $event->registrations()->with(['participant', 'roomAssignment'])->findOrFail($data['registration_id']);

        if (empty($data['room_id'])) {
            $result = $allocator->removeAssignment($registration);

            return response()->json($result, $result['ok'] ? 200 : 422);
        }

        $room = AccommodationRoom::with(['floor.block.site', 'activeAssignments.registration.participant'])->findOrFail($data['room_id']);
        abort_unless($room->floor->block->site->event_id === $event->id, 404);

        if (! $request->boolean('confirmed') && ($warning = $this->genderWarning($registration, $room))) {
            return response()->json(['ok' => false, 'confirm' => $warning], 409);
        }

        $previousRoom = $registration->roomAssignment?->accommodation_room_id;
        $result = $allocator->assignManually($registration, $room, $request->user()->id, false, false, $event->accommodation_published);
        if ($result['ok'] && $previousRoom !== $room->id) {
            // A new room means any room email already sent is out of date: let "Email rooms to attendees" send it again.
            $registration->roomAssignment()->update(['notification_sent_at' => null]);
        }

        return response()->json($result + ['room' => $room->label()], $result['ok'] ? 200 : 422);
    }

    private function genderWarning(EventRegistration $registration, AccommodationRoom $room): ?string
    {
        $gender = strtolower((string) $registration->participant->gender);
        if ($gender === '') {
            return null;
        }
        $restriction = strtolower((string) $room->effectiveGender());
        if ($restriction && ! str_starts_with($restriction, $gender[0])) {
            return "{$room->name} is set aside for {$restriction} guests. Put {$registration->participant->name} there anyway?";
        }
        $others = $room->activeAssignments->filter(fn ($assignment) => $assignment->event_registration_id !== $registration->id
            && ($other = strtolower((string) $assignment->registration?->participant?->gender)) !== '' && $other[0] !== $gender[0]);

        return $others->isNotEmpty()
            ? "{$room->name} already has guests of another gender. Put {$registration->participant->name} there anyway?"
            : null;
    }
}
