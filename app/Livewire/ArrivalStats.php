<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ArrivalStats extends Component
{
    public Event $event;

    public function render()
    {
        Gate::authorize('view', $this->event);
        $confirmed = $this->event->confirmedParticipants()->count();
        $arrived = Attendance::query()->where('event_id', $this->event->id)->where('day', 0)->count();

        return view('livewire.arrival-stats', compact('confirmed', 'arrived'));
    }
}
