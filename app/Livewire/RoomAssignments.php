<?php

namespace App\Livewire;

use App\Models\AccommodationRoom;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\RoomAllocationService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class RoomAssignments extends Component
{
    use WithPagination;

    public Event $event;

    public string $search = '';

    public bool $unassignedOnly = false;

    public bool $needsOnly = false;

    /** @var array<int, string> registration id => selected room id, applied only when "Assign" is clicked */
    public array $selectedRoom = [];

    /** @var array<int, bool> */
    public array $lockRoom = [];

    /** @var array<int, bool> */
    public array $emailNow = [];

    public ?string $flash = null;

    public string $flashType = 'success';

    protected $queryString = ['search' => ['except' => '']];

    public function mount(Event $event): void
    {
        $this->event = $event;
        Gate::authorize('update', $this->event);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedUnassignedOnly(): void
    {
        $this->resetPage();
    }

    public function updatedNeedsOnly(): void
    {
        $this->resetPage();
    }

    public function assign(int $registrationId, RoomAllocationService $allocator): void
    {
        Gate::authorize('update', $this->event);
        $registration = $this->event->registrations()->findOrFail($registrationId);
        $roomId = $this->selectedRoom[$registrationId] ?? null;

        if (! $roomId) {
            $this->flash = 'Choose a room before assigning.';
            $this->flashType = 'error';

            return;
        }

        $room = AccommodationRoom::with('floor.block.site')->findOrFail($roomId);
        abort_unless($room->floor->block->site->event_id === $this->event->id, 404);

        $result = $allocator->assignManually(
            $registration,
            $room,
            auth()->id(),
            (bool) ($this->lockRoom[$registrationId] ?? false),
            (bool) ($this->emailNow[$registrationId] ?? true),
            $this->event->accommodation_published
        );

        $this->flash = $result['message'];
        $this->flashType = $result['ok'] ? 'success' : 'error';
    }

    public function removeAssignment(int $registrationId, RoomAllocationService $allocator): void
    {
        Gate::authorize('update', $this->event);
        $registration = $this->event->registrations()->findOrFail($registrationId);
        $result = $allocator->removeAssignment($registration);

        $this->flash = $result['message'];
        $this->flashType = $result['ok'] ? 'success' : 'error';
        unset($this->selectedRoom[$registrationId]);
    }

    public function render(RoomAllocationService $allocator)
    {
        Gate::authorize('view', $this->event);

        $registrations = $this->event->registrations()
            ->with(['participant', 'roomAssignment.room.floor.block.site'])
            ->where('status', EventRegistration::STATUS_CONFIRMED)
            ->when($this->search !== '', function ($query) {
                $term = '%'.strtolower(trim($this->search)).'%';
                $query->where(function ($sub) use ($term) {
                    $sub->whereHas('participant', fn ($p) => $p->whereRaw('LOWER(name) LIKE ?', [$term])->orWhereRaw('LOWER(email) LIKE ?', [$term]))
                        ->orWhereHas('roomAssignment.room', fn ($r) => $r->whereRaw('LOWER(name) LIKE ?', [$term]));
                });
            })
            ->when($this->unassignedOnly, fn ($q) => $q->whereDoesntHave('roomAssignment'))
            ->when($this->needsOnly, fn ($q) => $q->where('accommodation_required', true))
            ->orderByDesc('accommodation_required')
            ->orderBy('registered_at')
            ->paginate(20);

        $this->event->load(['accommodationSites.blocks.floors.rooms' => fn ($q) => $q->withCount('activeAssignments')]);
        $rooms = $this->event->accommodationSites->flatMap->blocks->flatMap->floors->flatMap->rooms
            ->whereIn('status', [AccommodationRoom::STATUS_ACTIVE, AccommodationRoom::STATUS_RESERVED])
            ->sortBy('status')
            ->groupBy(fn ($room) => $room->floor->block->site->name.' / '.$room->floor->block->name);

        return view('livewire.room-assignments', [
            'registrations' => $registrations,
            'roomGroups' => $rooms,
            'allocator' => $allocator,
        ]);
    }
}
