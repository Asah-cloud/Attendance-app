<?php

namespace App\Services;

use App\Models\AccommodationRoom;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\RoomAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoomAllocationService
{
    /** @return array{proposals: Collection, unallocated: Collection} */
    public function preview(Event $event): array
    {
        $rooms = $this->eligibleRooms($event);
        $occupancy = $rooms->mapWithKeys(fn ($room) => [$room->id => $room->active_assignments_count]);
        $proposals = collect();
        $unallocated = collect();

        $registrations = $event->registrations()
            ->with(['participant', 'roomAssignment.room'])
            ->where('status', EventRegistration::STATUS_CONFIRMED)
            ->where('accommodation_required', true)
            ->whereDoesntHave('roomAssignment')
            ->orderByDesc('accessibility_required')
            ->orderBy('registered_at')
            ->get();

        foreach ($registrations as $registration) {
            $room = $rooms->first(function ($room) use ($registration, $occupancy) {
                return $occupancy[$room->id] < $room->capacity && $this->matches($registration, $room);
            });

            if (! $room) {
                $unallocated->push(['registration' => $registration, 'reason' => 'No active room has compatible restrictions and free capacity.']);

                continue;
            }

            $occupancy->put($room->id, $occupancy->get($room->id, 0) + 1);
            $proposals->push(['registration' => $registration, 'room' => $room, 'reason' => $this->reason($registration, $room)]);
        }

        return compact('proposals', 'unallocated');
    }

    /** @return array{assigned: int, unallocated: int} */
    public function commit(Event $event, ?int $userId): array
    {
        return DB::transaction(function () use ($event, $userId) {
            // Serialize allocation runs for this event and recalculate against locked rows.
            Event::query()->lockForUpdate()->findOrFail($event->id);
            $result = $this->preview($event);
            $assigned = 0;

            foreach ($result['proposals'] as $proposal) {
                $room = AccommodationRoom::query()->lockForUpdate()->findOrFail($proposal['room']->id);
                $used = $room->activeAssignments()->lockForUpdate()->count();
                if ($used >= $room->capacity) {
                    continue;
                }

                RoomAssignment::query()->updateOrCreate(
                    ['event_registration_id' => $proposal['registration']->id],
                    [
                        'accommodation_room_id' => $room->id,
                        'status' => 'assigned',
                        'method' => 'automatic',
                        'allocation_reason' => $proposal['reason'],
                        'assigned_by' => $userId,
                        'assigned_at' => now(),
                    ]
                );
                $assigned++;
            }

            return ['assigned' => $assigned, 'unallocated' => $result['unallocated']->count() + ($result['proposals']->count() - $assigned)];
        });
    }

    /**
     * Place a single confirmed registration into the best free, compatible room.
     * Cheap enough to run inline on confirmation — unlike commit(), it does not re-scan the whole event.
     */
    public function allocateOne(EventRegistration $registration, ?int $userId = null): ?RoomAssignment
    {
        return DB::transaction(function () use ($registration, $userId) {
            Event::query()->lockForUpdate()->findOrFail($registration->event_id);
            $registration->loadMissing('participant', 'roomAssignment');

            if ($registration->roomAssignment) {
                return $registration->roomAssignment;
            }

            $candidate = $this->eligibleRooms($registration->event)
                ->first(fn ($room) => $room->active_assignments_count < $room->capacity && $this->matches($registration, $room));

            if (! $candidate) {
                return null;
            }

            $room = AccommodationRoom::query()->lockForUpdate()->findOrFail($candidate->id);
            if ($room->activeAssignments()->lockForUpdate()->count() >= $room->capacity) {
                return null;
            }

            return RoomAssignment::create([
                'event_registration_id' => $registration->id,
                'accommodation_room_id' => $room->id,
                'status' => 'assigned',
                'method' => 'automatic',
                'allocation_reason' => $this->reason($registration, $candidate),
                'assigned_by' => $userId,
                'assigned_at' => now(),
            ]);
        });
    }

    private function eligibleRooms(Event $event): Collection
    {
        return AccommodationRoom::query()
            ->with(['floor.block.site'])
            ->withCount(['activeAssignments'])
            ->where('status', AccommodationRoom::STATUS_ACTIVE)
            ->whereHas('floor', fn ($query) => $query->where('is_active', true)
                ->whereHas('block', fn ($query) => $query->where('is_active', true)
                    ->whereHas('site', fn ($query) => $query->where('event_id', $event->id)->where('is_active', true))))
            ->join('accommodation_floors', 'accommodation_floors.id', '=', 'accommodation_rooms.accommodation_floor_id')
            ->join('accommodation_blocks', 'accommodation_blocks.id', '=', 'accommodation_floors.accommodation_block_id')
            ->orderBy('accommodation_blocks.priority')
            ->orderBy('accommodation_floors.priority')
            ->orderBy('accommodation_rooms.priority')
            ->orderBy('accommodation_rooms.id')
            ->select('accommodation_rooms.*')
            ->get();
    }

    /** Rooms this registration may still choose from: compatible, active, with a free bed. */
    public function selectableRooms(EventRegistration $registration): Collection
    {
        $registration->loadMissing('participant', 'roomAssignment');
        $currentRoomId = $registration->roomAssignment?->accommodation_room_id;

        return $this->eligibleRooms($registration->event)
            ->filter(function ($room) use ($registration, $currentRoomId) {
                $used = $room->active_assignments_count - ($room->id === $currentRoomId ? 1 : 0);

                return $used < $room->capacity && $this->matches($registration, $room);
            })
            ->values();
    }

    /** @return array{ok: bool, message: string} */
    public function claim(EventRegistration $registration, int $roomId): array
    {
        return DB::transaction(function () use ($registration, $roomId) {
            Event::query()->lockForUpdate()->findOrFail($registration->event_id);
            $registration->loadMissing('participant', 'roomAssignment');
            $assignment = $registration->roomAssignment;

            if ($assignment && in_array($assignment->status, ['checked_in', 'checked_out'], true)) {
                return ['ok' => false, 'message' => 'Your room can no longer be changed here. Please speak to an organiser.'];
            }
            if ($assignment?->is_locked) {
                return ['ok' => false, 'message' => 'Your room has been fixed by an organiser and cannot be changed here.'];
            }

            $room = AccommodationRoom::query()->with('floor.block.site')->lockForUpdate()->find($roomId);
            if (! $room
                || $room->floor->block->site->event_id !== $registration->event_id
                || $room->status !== AccommodationRoom::STATUS_ACTIVE
                || ! $room->floor->is_active || ! $room->floor->block->is_active || ! $room->floor->block->site->is_active) {
                return ['ok' => false, 'message' => 'That room is not available.'];
            }
            if (! $this->matches($registration, $room)) {
                return ['ok' => false, 'message' => 'That room does not match your requirements.'];
            }
            if ($room->activeAssignments()->where('event_registration_id', '!=', $registration->id)->lockForUpdate()->count() >= $room->capacity) {
                return ['ok' => false, 'message' => 'Sorry, that room was just taken. Please choose another.'];
            }

            RoomAssignment::updateOrCreate(
                ['event_registration_id' => $registration->id],
                [
                    'accommodation_room_id' => $room->id,
                    'status' => 'assigned',
                    'method' => 'self',
                    'allocation_reason' => 'Chosen by the attendee.',
                    'assigned_at' => now(),
                ]
            );

            return ['ok' => true, 'message' => 'Your room is confirmed: '.$room->label().'.'];
        });
    }

    public function matches(EventRegistration $registration, AccommodationRoom $room): bool
    {
        $participant = $registration->participant;
        $gender = $room->gender_restriction ?: $room->floor->block->gender_restriction;
        $category = $room->category_restriction ?: $room->floor->block->category_restriction;

        if ($gender && $this->normalizeGender($gender) !== $this->normalizeGender($participant->gender)) {
            return false;
        }
        if ($category && $this->normalizeText($category) !== $this->normalizeText($participant->category)) {
            return false;
        }
        if ($registration->accessibility_required && ! ($room->is_accessible || $room->floor->is_accessible)) {
            return false;
        }

        return true;
    }

    /** Fold common spellings of a gender to 'male' / 'female'; anything else passes through lower-cased. */
    private function normalizeGender(?string $value): ?string
    {
        $value = $this->normalizeText($value);

        return match (true) {
            in_array($value, ['m', 'male', 'man', 'boy', 'gentleman', 'brother', 'mr'], true) => 'male',
            in_array($value, ['f', 'female', 'woman', 'girl', 'lady', 'sister', 'mrs', 'ms', 'miss'], true) => 'female',
            default => $value,
        };
    }

    private function normalizeText(?string $value): ?string
    {
        $value = preg_replace('/\s+/', ' ', strtolower(trim((string) $value)));

        return $value !== '' ? $value : null;
    }

    private function reason(EventRegistration $registration, AccommodationRoom $room): string
    {
        $reasons = ['First compatible room by configured priority'];
        if ($registration->accessibility_required) {
            $reasons[] = 'accessible room/floor';
        }
        if ($room->gender_restriction || $room->floor->block->gender_restriction) {
            $reasons[] = 'gender matched';
        }
        if ($room->category_restriction || $room->floor->block->category_restriction) {
            $reasons[] = 'category matched';
        }

        return implode('; ', $reasons).'.';
    }
}
