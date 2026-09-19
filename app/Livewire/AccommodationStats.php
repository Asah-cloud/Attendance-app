<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class AccommodationStats extends Component
{
    public Event $event;

    public function render()
    {
        Gate::authorize('view', $this->event);

        $rooms = $this->event->accommodationSites()->with('blocks.floors.rooms')->get()
            ->flatMap(fn ($site) => $site->blocks)
            ->flatMap(fn ($block) => $block->floors)
            ->flatMap(fn ($floor) => $floor->rooms);
        $registrations = $this->event->registrations()
            ->where('status', EventRegistration::STATUS_CONFIRMED)
            ->with('roomAssignment')
            ->get();

        return view('livewire.accommodation-stats', [
            'beds' => $rooms->sum('capacity'),
            'rooms' => $rooms->count(),
            'required' => $registrations->where('accommodation_required', true)->count(),
            'assigned' => $registrations->filter(fn ($registration) => in_array($registration->roomAssignment?->status, ['assigned', 'checked_in'], true))->count(),
        ]);
    }
}
