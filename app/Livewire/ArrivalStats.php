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
        $confirmed = $this->event->expectedParticipants()->count();
        // Same rule as the reports: someone cancelled after arriving no longer counts.
        $arrived = Attendance::query()->countedFor($this->event)->where('day', 0)->distinct('participant_id')->count('participant_id');
        $unusedPlaceholders = $this->event->unusedPlaceholderIds()->count();

        return view('livewire.arrival-stats', compact('confirmed', 'arrived', 'unusedPlaceholders'));
    }
}
