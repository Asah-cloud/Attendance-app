<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class AttendanceSearch extends Component
{
    use WithPagination;

    public $event;

    public $search = '';

    public $attendedUserIds = [];

    public $selectedDay = 1; // This is the property name we must use everywhere

    protected $queryString = [
        'selectedDay' => ['except' => 1],
        'search' => ['except' => ''],
    ];

    protected $listeners = [
        'refreshAttendeeList' => '$refresh',
    ];

    public function mount($event)
    {
        $this->event = $event instanceof Event
            ? $event
            : Event::findOrFail($event);

        Gate::authorize('view', $this->event);

        $this->selectedDay = (int) request()->query('day', 1);
        $this->loadAttendedUserIds();
    }

    public function setDay($val)
    {
        $this->selectedDay = $val === 'all' ? 'all' : (int) $val;
        $this->loadAttendedUserIds();
        $this->resetPage();
    }

    // Fixed method name to match property updates
    public function updatedSelectedDay()
    {
        $this->loadAttendedUserIds();
        $this->resetPage();
    }

    public function loadAttendedUserIds()
    {
        $query = Attendance::query()->where('event_id', $this->event->id);

        // Changed $this->day to $this->selectedDay
        if ($this->selectedDay !== 'all') {
            $query->where('day', $this->selectedDay);
        }

        $this->attendedUserIds = $query->pluck('participant_id')->toArray();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function toggleAttendance(int $participantId)
    {
        Gate::authorize('update', $this->event);
        $this->event->confirmedParticipants()->findOrFail($participantId);

        if ($this->selectedDay === 'all') {
            session()->flash('error', 'Select a specific event day before changing attendance.');

            return;
        }
        if (! $this->event->canMarkAttendanceForDay((int) $this->selectedDay)) {
            session()->flash('error', 'Attendance can only be changed for a day that has started while the event is active.');

            return;
        }

        // Changed $this->day to $this->selectedDay
        $currentDay = $this->selectedDay;

        $query = Attendance::query()
            ->where('event_id', $this->event->id)
            ->where('participant_id', $participantId);

        if ($currentDay !== 'all') {
            $query->where('day', $currentDay);
        }

        $attendance = $query->first();

        if ($attendance) {
            $attendance->id ? Attendance::destroy($attendance->id) : null;
        } else {
            Attendance::create([
                'event_id' => $this->event->id,
                'participant_id' => $participantId,
                'day' => $currentDay,
            ]);
        }

        $this->loadAttendedUserIds();
    }

    public function deleteUser(int $participantId)
    {
        Gate::authorize('update', $this->event);
        $this->event->confirmedParticipants()->findOrFail($participantId);
        $this->event->registrations()->where('participant_id', $participantId)->delete();
        session()->flash('message', '🗑️ Member removed successfully.');
    }

    public function render()
    {
        Gate::authorize('view', $this->event);
        $words = explode(' ', trim($this->search));

        $users = $this->event->confirmedParticipants()
            ->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    if (! empty($word)) {
                        $wordLower = '%'.strtolower($word).'%';
                        $q->where(function ($sub) use ($wordLower) {
                            $sub->whereRaw('LOWER(name) LIKE ?', [$wordLower])
                                ->orWhere('phone', 'like', $wordLower)
                                ->orWhereRaw('LOWER(category) LIKE ?', [$wordLower]);
                        });
                    }
                }
            })
            ->paginate(15);

        // We automatically pass public properties to the view,
        // so $selectedDay is now available natively inside your blade view.
        return view('livewire.attendance-search', [
            'users' => $users,
        ]);
    }
}
