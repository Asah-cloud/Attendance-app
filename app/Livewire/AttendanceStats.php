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

    public int $session = 1;

    protected $listeners = [
        'attendanceStatsChanged' => '$refresh',
    ];

    public function mount(Event $event, int $day, int $session = 1): void
    {
        Gate::authorize('view', $event);

        $this->event = $event;
        $this->day = $day;
        $this->session = $session;
    }

    public function render()
    {
        Gate::authorize('view', $this->event);

        $participantStaffIds = $this->event->persistentParticipantStaffIds();
        $participantStaffCount = $participantStaffIds->count();
        $dailyAttendeeCount = Attendance::query()
            ->countedFor($this->event)
            ->where('day', $this->day)
            ->where('session', $this->session)
            ->distinct('participant_id')
            ->count('participant_id');
        $cumulativeAttendeeCount = $this->day < 1 ? 0 : Attendance::query()
            ->countedFor($this->event)
            ->whereBetween('day', [1, $this->day])
            ->distinct('participant_id')
            ->count('participant_id');

        return view('livewire.attendance-stats', [
            'totalMembers' => $this->event->attendanceEligibleParticipants()->count(),
            'participantStaffCount' => $participantStaffCount,
            'presentCount' => $dailyAttendeeCount + $participantStaffCount,
            'totalPresentCount' => $this->day < 1 ? 0 : $cumulativeAttendeeCount + $participantStaffCount,
        ]);
    }
}
