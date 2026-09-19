<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class StaffCheckInStats extends Component
{
    public Event $event;

    public function render()
    {
        Gate::authorize('view', $this->event);
        $assigned = $this->event->confirmedStaff()->count();
        $checkedIn = Attendance::query()->where('event_id', $this->event->id)->where('day', -1)->count();

        return view('livewire.staff-check-in-stats', compact('assigned', 'checkedIn'));
    }
}
