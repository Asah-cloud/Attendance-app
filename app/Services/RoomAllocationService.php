<?php

namespace App\Services;

use App\Models\AccommodationRoom;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\RoomAssignment;
use App\Notifications\Concerns\NotifiesPerChannel;
use App\Notifications\RoomAssigned;
use App\Support\RoomRange;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoomAllocationService
{
    /** @return array{proposals: Collection, unallocated: Collection} */
    public function preview(Event $event, ?string $category = null, ?string $gender = null): array
    {
        $rooms = $this->eligibleRooms($event);
        $occupancy = $rooms->mapWithKeys(fn ($room) => [$room->id => (int) $room->active_assignments_count]);
        // Which room_group values already sit in each room — seeded from existing occupants,
        // then updated as this run proposes new placements, so a whole group clusters together
        // even across multiple people processed in the same pass.
        $roomGroups = $rooms->mapWithKeys(fn ($room) => [$room->id => $this->existingGroups($room)])->all();
        // Likewise each room's gender: its restriction, else whoever sleeps there, else whoever this run puts there first.
        $roomGenders = $rooms->mapWithKeys(fn ($room) => [$room->id => $this->roomGender($room)])->all();
        $proposals = collect();
        $unallocated = collect();

        $registrations = $event->registrations()
            ->with(['participant', 'roomAssignment.room'])
            ->where('status', EventRegistration::STATUS_CONFIRMED)
            ->where('accommodation_required', true)
            ->whereDoesntHave('roomAssignment')
            ->orderByDesc('accessibility_required')
            ->orderBy('registered_at')
            ->get()
            ->filter(fn (EventRegistration $registration) => $this->matchesParticipantFilter($registration, $category, $gender))
            ->values();

        foreach ($registrations as $registration) {
            $group = $this->normalizeText($registration->participant->room_group);
            $room = $this->pickRoom(
                $rooms,
                $registration,
                fn ($room) => $occupancy[$room->id],
                fn ($room) => $roomGroups[$room->id] ?? [],
                fn ($room) => $roomGenders[$room->id] ?? null
            );

            if (! $room) {
                $unallocated->push(['registration' => $registration, 'reason' => 'No active room has compatible restrictions and free capacity.']);

                continue;
            }

            $groupMatched = $group && in_array($group, $roomGroups[$room->id] ?? [], true);
            $occupancy->put($room->id, $occupancy->get($room->id, 0) + 1);
            $roomGenders[$room->id] ??= $this->normalizeGender($registration->participant->gender);
            if ($group) {
                $roomGroups[$room->id] = array_merge($roomGroups[$room->id] ?? [], [$group]);
            }
            $proposals->push(['registration' => $registration, 'room' => $room, 'reason' => $this->reason($registration, $room, $groupMatched)]);
        }

        return compact('proposals', 'unallocated');
    }

    /**
     * From a rooms list (each annotated with the fields matches()/occupancy need), pick the best
     * free, compatible room for one registration. A room never takes someone of the other gender
     * than its restriction or its occupants. Someone with a room_group goes to a room already
     * holding that group; someone without one fills a part-filled, group-free room. Next comes an
     * empty room (a fresh room for a new group), and any compatible room with a free bed is the
     * last resort.
     */
    private function pickRoom(Collection $rooms, EventRegistration $registration, callable $occupancyOf, callable $groupsOf, callable $genderOf): ?AccommodationRoom
    {
        $group = $this->normalizeText($registration->participant->room_group);
        $gender = $this->normalizeGender($registration->participant->gender);
        $free = $rooms->filter(fn ($room) => $occupancyOf($room) < $room->capacity
            && $this->matches($registration, $room)
            && (! $gender || ! $genderOf($room) || $genderOf($room) === $gender));

        $preferred = $group
            ? $free->first(fn ($room) => in_array($group, $groupsOf($room), true))
            : $free->first(fn ($room) => (int) $occupancyOf($room) > 0 && $groupsOf($room) === []);

        return $preferred
            ?? $free->first(fn ($room) => (int) $occupancyOf($room) === 0)
            ?? $free->first();
    }

    /**
     * A room's gender: its room, floor or building restriction, else the gender of whoever is already in it
     * (leaving out $ignoreRegistrationId, so someone moving out of a room doesn't count towards it).
     */
    private function roomGender(AccommodationRoom $room, ?int $ignoreRegistrationId = null): ?string
    {
        return $this->normalizeGender($room->effectiveGender())
            ?: $room->activeAssignments
                ->reject(fn ($assignment) => $assignment->event_registration_id === $ignoreRegistrationId)
                ->map(fn ($assignment) => $this->normalizeGender($assignment->registration?->participant?->gender))->filter()->first();
    }

    /** Whether this registration's gender fits the room's, as automatic assignment requires. Unknown genders fit anywhere. */
    private function fitsRoomGender(EventRegistration $registration, AccommodationRoom $room): bool
    {
        $gender = $this->normalizeGender($registration->participant->gender);
        $roomGender = $this->roomGender($room, $registration->id);

        return ! $gender || ! $roomGender || $roomGender === $gender;
    }

    /** The distinct, normalized room_group values already occupying a room. */
    private function existingGroups(AccommodationRoom $room): array
    {
        return $room->activeAssignments
            ->map(fn ($assignment) => $this->normalizeText($assignment->registration?->participant?->room_group))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return array{assigned: int, unallocated: int} */
    public function commit(Event $event, ?int $userId, ?string $category = null, ?string $gender = null): array
    {
        return DB::transaction(function () use ($event, $userId, $category, $gender) {
            // Serialize allocation runs for this event and recalculate against locked rows.
            Event::query()->lockForUpdate()->findOrFail($event->id);
            $result = $this->preview($event, $category, $gender);
            $assigned = 0;

            foreach ($result['proposals'] as $proposal) {
                $room = AccommodationRoom::query()->lockForUpdate()->findOrFail($proposal['room']->id);
                // Postgres rejects "SELECT count(*) ... FOR UPDATE"; lock the rows, then count in PHP.
                $used = $room->activeAssignments()->lockForUpdate()->get()->count();
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
     * Place a chosen list of registrations (a room-plan import) at most $perRoom to a room, even
     * where rooms have more beds; the spare beds stay free for later. People are grouped by
     * gender, accessibility need and room group, and each group of up to $perRoom goes into an
     * empty room where one is free, else the compatible room with the most space. A room never
     * mixes genders. $where limits the rooms used (site_id, block_id, floor_id, room_ids); rooms
     * on a chosen floor or picked one by one fill lowest room number first. $requested names a
     * room per registration id (the list's Room column): they go there instead, wherever it is,
     * if it has a bed and suits them. People who already have a room are left where they are.
     *
     * @param  Collection<int, EventRegistration>  $registrations
     * @param  array{site_id?: ?int, block_id?: ?int, floor_id?: ?int, room_ids?: ?list<int>}  $where
     * @param  array<int, string>  $requested
     * @return array{proposals: Collection, unallocated: Collection, alreadyRoomed: Collection, freeBeds: int, toPlace: int}
     */
    public function planBatch(Event $event, Collection $registrations, int $perRoom, array $where = [], array $requested = []): array
    {
        $registrations->loadMissing(['participant', 'roomAssignment.room.floor.block.site']);
        $alreadyRoomed = $registrations->filter(fn (EventRegistration $registration) => $registration->roomAssignment)->values();

        $allRooms = $this->eligibleRooms($event);
        $rooms = $allRooms
            ->filter(fn (AccommodationRoom $room) => (empty($where['site_id']) || $room->floor->block->accommodation_site_id === $where['site_id'])
                && (empty($where['block_id']) || $room->floor->accommodation_block_id === $where['block_id'])
                && (empty($where['floor_id']) || $room->accommodation_floor_id === $where['floor_id'])
                && (empty($where['room_ids']) || in_array($room->id, $where['room_ids'], true)))
            ->values();
        if (! empty($where['floor_id']) || ! empty($where['room_ids'])) {
            // Rooms the manager pointed at fill along the corridor rather than by setup priority.
            $rooms = $rooms->sort(fn ($a, $b) => strnatcasecmp($a->name, $b->name))->values();
        }
        $occupancy = $allRooms->mapWithKeys(fn ($room) => [$room->id => (int) $room->active_assignments_count])->all();
        $roomGender = $allRooms->mapWithKeys(fn ($room) => [$room->id => $this->roomGender($room)])->all();

        $proposals = collect();
        $unallocated = collect();
        $waiting = $registrations->reject(fn (EventRegistration $registration) => $registration->roomAssignment);

        // People the list names a room for go there first, outside the chosen place if need be.
        [$named, $waiting] = $waiting->partition(fn (EventRegistration $registration) => filled($requested[$registration->id] ?? null));
        foreach ($named as $registration) {
            $wanted = trim($requested[$registration->id]);
            $found = RoomRange::find($allRooms, $wanted);
            $room = $found->count() === 1 ? $found->first() : null;
            $gender = $this->normalizeGender($registration->participant->gender);
            $problem = match (true) {
                $found->isEmpty() => "Room {$wanted} on the list was not found, or is closed.",
                $found->count() > 1 => "More than one room matches {$wanted}: write it as on the room list.",
                $occupancy[$room->id] >= $room->capacity => "Room {$wanted} on the list is full.",
                ! $this->matches($registration, $room) || ($roomGender[$room->id] && $gender && $roomGender[$room->id] !== $gender) => "Room {$wanted} on the list does not suit them (gender, category or access).",
                default => null,
            };
            if ($problem) {
                $unallocated->push(['registration' => $registration, 'reason' => $problem]);

                continue;
            }
            $proposals->push(['registration' => $registration, 'room' => $room, 'reason' => 'Room plan: room named on the list.']);
            $occupancy[$room->id]++;
            $roomGender[$room->id] ??= $gender;
        }

        $queue = $waiting
            ->sortBy([
                fn ($a, $b) => (int) $b->accessibility_required <=> (int) $a->accessibility_required,
                fn ($a, $b) => (string) $this->normalizeGender($a->participant->gender) <=> (string) $this->normalizeGender($b->participant->gender),
                fn ($a, $b) => (string) $this->normalizeText($a->participant->room_group) <=> (string) $this->normalizeText($b->participant->room_group),
                fn ($a, $b) => strcasecmp((string) $a->participant->name, (string) $b->participant->name),
            ])->values();
        $toPlace = $queue->count();
        $freeBeds = $rooms->sum(fn ($room) => max(0, $room->capacity - $occupancy[$room->id]));

        $groups = $queue->groupBy(fn ($registration) => $this->normalizeGender($registration->participant->gender).'|'.(int) $registration->accessibility_required)
            ->flatMap(fn (Collection $bucket) => $bucket->chunk($perRoom)->map->values());

        foreach ($groups as $pending) {
            while ($pending->isNotEmpty()) {
                $first = $pending->first();
                $gender = $this->normalizeGender($first->participant->gender);
                $candidates = $rooms->filter(fn ($room) => $occupancy[$room->id] < $room->capacity
                    && $this->matches($first, $room)
                    && (! $roomGender[$room->id] || ! $gender || $roomGender[$room->id] === $gender));

                $room = $candidates->first(fn ($room) => $occupancy[$room->id] === 0 && $room->capacity >= $pending->count())
                    ?? $candidates->first(fn ($room) => $room->capacity - $occupancy[$room->id] >= $pending->count())
                    ?? $candidates->sortByDesc(fn ($room) => $room->capacity - $occupancy[$room->id])->first();

                if (! $room) {
                    $place = array_filter($where) ? ' in the chosen location' : '';
                    $pending->each(fn ($registration) => $unallocated->push(['registration' => $registration, 'reason' => "No free compatible room{$place}."]));

                    break;
                }

                $placed = $pending->filter(fn ($registration) => $this->matches($registration, $room))->take($room->capacity - $occupancy[$room->id]);
                foreach ($placed as $registration) {
                    $proposals->push(['registration' => $registration, 'room' => $room, 'reason' => "Room plan: up to {$perRoom} per room, ".$this->reason($registration, $room)]);
                }
                $occupancy[$room->id] += $placed->count();
                $roomGender[$room->id] ??= $gender;
                $placedIds = $placed->pluck('id')->all();
                $pending = $pending->reject(fn ($registration) => in_array($registration->id, $placedIds, true))->values();
            }
        }

        return compact('proposals', 'unallocated', 'alreadyRoomed', 'freeBeds', 'toPlace');
    }

    /**
     * Save a planBatch() result, rechecking each room's free beds under a lock.
     *
     * @return array{assigned: int, unallocated: int}
     */
    public function commitPlan(Event $event, array $plan, ?int $userId): array
    {
        return DB::transaction(function () use ($event, $plan, $userId) {
            Event::query()->lockForUpdate()->findOrFail($event->id);
            $assigned = 0;

            foreach ($plan['proposals'] as $proposal) {
                $room = AccommodationRoom::query()->lockForUpdate()->findOrFail($proposal['room']->id);
                if ($room->activeAssignments()->lockForUpdate()->get()->count() >= $room->capacity) {
                    continue;
                }

                RoomAssignment::query()->updateOrCreate(
                    ['event_registration_id' => $proposal['registration']->id],
                    ['accommodation_room_id' => $room->id, 'status' => 'assigned', 'method' => 'plan',
                        'allocation_reason' => $proposal['reason'], 'assigned_by' => $userId, 'assigned_at' => now()]
                );
                $assigned++;
            }

            return ['assigned' => $assigned, 'unallocated' => $plan['unallocated']->count() + ($plan['proposals']->count() - $assigned)];
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

            $rooms = $this->eligibleRooms($registration->event);
            $candidate = $this->pickRoom(
                $rooms,
                $registration,
                fn ($room) => (int) $room->active_assignments_count,
                fn ($room) => $this->existingGroups($room),
                fn ($room) => $this->roomGender($room)
            );

            if (! $candidate) {
                return null;
            }

            $group = $this->normalizeText($registration->participant->room_group);
            $groupMatched = $group && in_array($group, $this->existingGroups($candidate), true);

            $room = AccommodationRoom::query()->lockForUpdate()->findOrFail($candidate->id);
            if ($room->activeAssignments()->lockForUpdate()->get()->count() >= $room->capacity) {
                return null;
            }

            return RoomAssignment::create([
                'event_registration_id' => $registration->id,
                'accommodation_room_id' => $room->id,
                'status' => 'assigned',
                'method' => 'automatic',
                'allocation_reason' => $this->reason($registration, $candidate, $groupMatched),
                'assigned_by' => $userId,
                'assigned_at' => now(),
            ]);
        });
    }

    private function eligibleRooms(Event $event): Collection
    {
        // select() before withCount(): a later select() would drop the count, and every room would look empty.
        return AccommodationRoom::query()
            ->select('accommodation_rooms.*')
            ->with(['floor.block.site', 'activeAssignments.registration.participant'])
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
            ->get();
    }

    /** Rooms this registration may still choose from: compatible, active, with a free bed, and no one of another gender in it. */
    public function selectableRooms(EventRegistration $registration): Collection
    {
        $registration->loadMissing('participant', 'roomAssignment');
        $currentRoomId = $registration->roomAssignment?->accommodation_room_id;

        return $this->eligibleRooms($registration->event)
            ->filter(function ($room) use ($registration, $currentRoomId) {
                $used = $room->active_assignments_count - ($room->id === $currentRoomId ? 1 : 0);

                return $used < $room->capacity && $this->matches($registration, $room) && $this->fitsRoomGender($registration, $room);
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
            if ($room->activeAssignments()->where('event_registration_id', '!=', $registration->id)->lockForUpdate()->get()->count() >= $room->capacity) {
                return ['ok' => false, 'message' => 'Sorry, that room was just taken. Please choose another.'];
            }
            // Rechecked under the lock: someone of another gender may have taken a bed since the picker loaded.
            if (! $this->fitsRoomGender($registration, $room->load('activeAssignments.registration.participant'))) {
                return ['ok' => false, 'message' => 'Sorry, guests of another gender are staying in that room. Please choose another.'];
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

    /**
     * Manually place a registration in a specific room - shared by the room board, the
     * accommodation controller's plain form and the Livewire room-assignment table so all enforce
     * the same rules. Category and step-free settings can be overridden; gender can't
     * (see manualGenderCheck), though an empty room switches to the person's gender.
     *
     * @return array{ok: bool, message: string, room_gender?: string}
     */
    public function assignManually(EventRegistration $registration, AccommodationRoom $room, int $assignedByUserId, bool $isLocked, bool $sendNotification, bool $eventPublished): array
    {
        $assignment = $registration->roomAssignment;
        if ($assignment?->status === 'checked_in' && $assignment->accommodation_room_id !== $room->id) {
            return ['ok' => false, 'message' => 'Checked-in attendees cannot be moved.'];
        }
        if ($room->status === AccommodationRoom::STATUS_CLOSED) {
            return ['ok' => false, 'message' => 'That room is closed. Set it to active or reserved first.'];
        }
        if (! $room->floor->is_active || ! $room->floor->block->is_active) {
            return ['ok' => false, 'message' => 'That room is on an inactive floor or block. Reactivate it first.'];
        }
        if ($room->activeAssignments()->where('event_registration_id', '!=', $registration->id)->count() >= $room->capacity) {
            return ['ok' => false, 'message' => 'That room is already full.'];
        }
        $genderCheck = $this->manualGenderCheck($registration, $room);
        if (! $genderCheck['ok']) {
            return ['ok' => false, 'message' => $genderCheck['message']];
        }
        if (isset($genderCheck['switchTo'])) {
            $room->update(['gender_restriction' => $genderCheck['switchTo']]);
        }

        RoomAssignment::updateOrCreate(['event_registration_id' => $registration->id], [
            'accommodation_room_id' => $room->id,
            'status' => 'assigned',
            'method' => 'manual',
            'is_locked' => $isLocked,
            'allocation_reason' => 'Manually assigned by a manager.',
            'assigned_by' => $assignedByUserId,
            'assigned_at' => now(),
        ]);
        $registration->update(['accommodation_required' => true]);

        if ($eventPublished && $sendNotification) {
            $fresh = $registration->roomAssignment()->firstOrFail();
            $fresh->loadMissing('registration.participant');
            NotifiesPerChannel::send($fresh->registration->participant, new RoomAssigned($fresh));
            $fresh->update(['notification_sent_at' => now()]);
        }

        return isset($genderCheck['switchTo'])
            ? ['ok' => true, 'message' => "Room assigned. {$room->name} is now for ".strtolower($genderCheck['switchTo']).' guests.', 'room_gender' => $genderCheck['switchTo']]
            : ['ok' => true, 'message' => 'Room assigned.'];
    }

    /**
     * Keeps a room to one gender when a manager places someone by hand. Someone of the other
     * gender already staying there means no. A room kept for the other gender (by its own, its
     * floor's or its building's setting) with none of them in it switches its own setting to this
     * person's gender. People whose gender isn't male or female can go anywhere.
     *
     * @return array{ok: bool, message?: string, switchTo?: string}
     */
    private function manualGenderCheck(EventRegistration $registration, AccommodationRoom $room): array
    {
        $registration->loadMissing('participant');
        $gender = $this->normalizeGender($registration->participant->gender);
        if (! in_array($gender, ['male', 'female'], true)) {
            return ['ok' => true];
        }

        $other = $room->activeAssignments()->where('event_registration_id', '!=', $registration->id)->with('registration.participant')->get()
            ->map(fn ($assignment) => $this->normalizeGender($assignment->registration?->participant?->gender))
            ->first(fn ($value) => in_array($value, ['male', 'female'], true) && $value !== $gender);
        if ($other) {
            return ['ok' => false, 'message' => "{$room->name} has {$other} guests, so {$registration->participant->name} can't go there."];
        }

        $restriction = $this->normalizeGender($room->effectiveGender());

        return $restriction && $restriction !== $gender ? ['ok' => true, 'switchTo' => ucfirst($gender)] : ['ok' => true];
    }

    /** @return array{ok: bool, message: string} */
    public function removeAssignment(EventRegistration $registration): array
    {
        if ($registration->roomAssignment?->status === 'checked_in') {
            return ['ok' => false, 'message' => 'Checked-in assignments cannot be removed.'];
        }
        $registration->roomAssignment?->delete();

        return ['ok' => true, 'message' => 'Room assignment removed.'];
    }

    public function matches(EventRegistration $registration, AccommodationRoom $room): bool
    {
        if (! $this->fitsRestrictions($registration, $room)) {
            return false;
        }
        if ($registration->accessibility_required && ! ($room->is_accessible || $room->floor->is_accessible)) {
            return false;
        }

        return true;
    }

    /** Whether this registration fits the room's gender and category, set on the room, its floor or its building. */
    private function fitsRestrictions(EventRegistration $registration, AccommodationRoom $room): bool
    {
        $participant = $registration->participant;
        $gender = $room->effectiveGender();
        $category = $room->effectiveCategory();

        return (! $gender || $this->normalizeGender($gender) === $this->normalizeGender($participant->gender))
            && (! $category || $this->normalizeText($category) === $this->normalizeText($participant->category));
    }

    /**
     * How many people already in these rooms don't fit their room's gender or category - after a
     * manager changes a building's, floor's or room's setting. They are left where they are.
     *
     * @param  Collection<int, AccommodationRoom>  $rooms
     */
    public function occupantsNotFitting(Collection $rooms): int
    {
        return EloquentCollection::make($rooms->all())->loadMissing('floor.block', 'activeAssignments.registration.participant')
            ->sum(fn (AccommodationRoom $room) => $room->activeAssignments
                ->filter(fn ($assignment) => $assignment->registration && ! $this->fitsRestrictions($assignment->registration, $room))
                ->count());
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

    /** Limit a bulk run to a participant category and/or gender, when selected. */
    private function matchesParticipantFilter(EventRegistration $registration, ?string $category, ?string $gender): bool
    {
        $participant = $registration->participant;

        return (! $this->normalizeText($category) || $this->normalizeText($category) === $this->normalizeText($participant->category))
            && (! $this->normalizeGender($gender) || $this->normalizeGender($gender) === $this->normalizeGender($participant->gender));
    }

    private function reason(EventRegistration $registration, AccommodationRoom $room, bool $groupMatched = false): string
    {
        $reasons = ['First compatible room by configured priority'];
        if ($groupMatched) {
            $reasons[] = 'kept with their room group';
        }
        if ($registration->accessibility_required) {
            $reasons[] = 'accessible room/floor';
        }
        if ($room->effectiveGender()) {
            $reasons[] = 'gender matched';
        }
        if ($room->effectiveCategory()) {
            $reasons[] = 'category matched';
        }

        return implode('; ', $reasons).'.';
    }
}
