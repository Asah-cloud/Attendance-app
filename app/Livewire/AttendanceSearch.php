<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\Attendance;
use Livewire\Component;
use Livewire\WithPagination;

class AttendanceSearch extends Component
{
    use WithPagination;

    public $event;
    public $search = '';
    public $attendedUserIds = [];
    public $day = 1;

    protected $queryString = [
        'day' => ['except' => 1],
        'search' => ['except' => '']
    ];

    public function mount($event)
    {
        $this->event = $event instanceof \App\Models\Event 
            ? $event 
            : \App\Models\Event::findOrFail($event);

        $this->day = (int) request()->query('day', 1);
        $this->loadAttendedUserIds();
    }
    public function setDay($val) {
        $this->day = $val === 'all' ? 'all' : (int)$val;
        $this->loadAttendedUserIds();
        $this->resetPage();
    }

    /**
     * Fix: Ensures that if the 'day' property changes via 
     * URL or internal update, we refresh the list immediately.
     */
    public function updatedDay()
    {
        $this->loadAttendedUserIds();
        $this->resetPage();
    }

    public function loadAttendedUserIds()
    {
        $this->attendedUserIds = Attendance::where('event_id', $this->event->id)
            ->where('day', $this->day)
            ->pluck('user_id')
            ->toArray();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function toggleAttendance($userId)
    {
        if ($this->event->status === 'closed') {
            session()->flash('error', 'This event is closed.');
            return;
        }

        if ($this->event->status === 'upcoming') {
            session()->flash('error', 'Event has not started yet.');
            return;
        }

        $currentDay = $this->day;

        $attendance = Attendance::where('event_id', $this->event->id)
            ->where('user_id', $userId)
            ->where('day', $currentDay)
            ->first();

        if ($attendance) {
            $attendance->delete();
        } else {
            Attendance::create([
                'event_id' => $this->event->id,
                'user_id' => $userId,
                'day' => $currentDay,
            ]);
        }

        $this->loadAttendedUserIds();
    }

    public function render()
    {
        $words = explode(' ', trim($this->search));

        $users = $this->event->users()
            ->where(function($q) use ($words) {
                foreach ($words as $word) {
                    if (!empty($word)) {
                        $wordLower = '%' . strtolower($word) . '%';
                        $q->where(function($sub) use ($wordLower) {
                            $sub->whereRaw('LOWER(name) LIKE ?', [$wordLower])
                                ->orWhere('phone', 'like', $wordLower)
                                ->orWhereRaw('LOWER(category) LIKE ?', [$wordLower]);
                        });
                    }
                }
            })
            ->paginate(15);

        return view('livewire.attendance-search', [
            'users' => $users,
        ]);
    }
}