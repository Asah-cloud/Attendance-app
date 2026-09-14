<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Event;
use App\Services\ApplicationCache;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class AttendanceSearch extends Component
{
    use WithPagination;

    /** The day value recorded for a support-staff check-in - a fixed sentinel, distinct from
     *  arrival (day 0) and the event's numbered attendance days (day >= 1). */
    private const STAFF_CHECKIN_DAY = -1;

    public $event;

    public $search = '';

    public $attendedUserIds = [];

    public $selectedDay = 1; // This is the property name we must use everywhere

    public string $mode = 'attendance';

    protected $queryString = [
        'selectedDay' => ['except' => 1],
        'search' => ['except' => ''],
    ];

    protected $listeners = [
        'refreshAttendeeList' => '$refresh',
    ];

    public function mount($event, ?int $day = null, string $mode = 'attendance')
    {
        $this->event = $event instanceof Event
            ? $event
            : Event::findOrFail($event);

        Gate::authorize('view', $this->event);

        $this->mode = $mode;
        $this->selectedDay = $mode === 'staff' ? self::STAFF_CHECKIN_DAY : ($day ?? (int) request()->query('day', 1));
        $this->loadAttendedUserIds();
    }

    /** The participant relation this mode searches, toggles and validates against. */
    private function participantsQuery(): BelongsToMany
    {
        return match ($this->mode) {
            'staff' => $this->event->confirmedStaff(),
            'arrival' => $this->event->confirmedParticipants(),
            default => $this->event->attendanceEligibleParticipants(),
        };
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
        $this->participantsQuery()->findOrFail($participantId);

        if ($this->mode === 'staff') {
            if ($this->event->isClosed()) {
                $this->dispatch('notify', message: 'This event is closed.', type: 'error');

                return;
            }
        } else {
            if ($this->selectedDay === 'all') {
                $this->dispatch('notify', message: 'Select a specific event day before changing attendance.', type: 'error');

                return;
            }
            if (! $this->event->canMarkAttendanceForDay((int) $this->selectedDay)) {
                $this->dispatch('notify', message: 'Attendance can only be changed for a day that has started while the event is active.', type: 'error');

                return;
            }
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

        app(ApplicationCache::class)->invalidateEvent($this->event->id, $this->event->company_id);
        $this->loadAttendedUserIds();
    }

    public function deleteUser(int $participantId)
    {
        Gate::authorize('update', $this->event);
        $this->participantsQuery()->findOrFail($participantId);
        $this->event->registrations()->where('participant_id', $participantId)->delete();
        $this->dispatch('notify', message: 'Member removed successfully.', type: 'success');
    }

    public function render()
    {
        Gate::authorize('view', $this->event);
        $words = explode(' ', trim($this->search));

        $users = $this->participantsQuery()
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
