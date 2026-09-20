<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class AttendanceStats extends Component
{
    public Event $event;

    public int $day;

    protected $listeners = [
        'attendanceStatsChanged' => '$refresh',
    ];

    public function mount(Event $event, int $day): void
    {
        Gate::authorize('view', $event);

        $this->event = $event;
        $this->day = $day;
    }

    public function render()
    {
        Gate::authorize('view', $this->event);

        $participantStaffIds = $this->event->persistentParticipantStaffIds();
        $participantStaffCount = $participantStaffIds->count();
        $dailyAttendeeCount = Attendance::query()
            ->where('event_id', $this->event->id)
            ->where('day', $this->day)
            ->whereHas('participant', fn ($query) => $query->where('is_support_staff', false))
            ->count();

        return view('livewire.attendance-stats', [
            'totalMembers' => $this->event->attendanceEligibleParticipants()->count(),
            'participantStaffCount' => $participantStaffCount,
            'presentCount' => $dailyAttendeeCount + $participantStaffCount,
        ]);
    }
}
