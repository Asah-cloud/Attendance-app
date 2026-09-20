<?php

namespace App\Livewire;

use App\Models\Event;
use App\Services\ApplicationCache;
use App\Services\ParticipantRegistrationService;
use App\Services\RegistrationLifecycleService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class AddWalkInModal extends Component
{
    public Event $event;

    public $showModal = false;

    // Form fields
    public $name = '';

    public $email = '';

    public $phone = '';

    public $category = '';

    public $room_group = '';

    // Listen for custom open events from the parent view
    protected $listeners = ['openWalkInModal' => 'openModal'];

    public function openModal()
    {
        $this->showModal = true;
    }

    public function registerWalkIn()
    {
        Gate::authorize('update', $this->event);

        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:30',
            'room_group' => 'nullable|string|max:255',
        ]);

        $category = ! empty(trim($this->category)) ? trim($this->category) : 'Member';

        [, $registration] = app(ParticipantRegistrationService::class)->register($this->event, [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'category' => $category,
            'room_group' => $this->room_group,
        ], 'walk_in');

        app(RegistrationLifecycleService::class)->notify($registration, 'confirmed');

        app(ApplicationCache::class)->invalidateEvent($this->event->id, $this->event->company_id);

        $this->reset(['name', 'email', 'phone', 'category', 'room_group', 'showModal']);

        // Tell the parent page to refresh its attendee list grid
        $this->dispatch('refreshAttendeeList');
        $this->dispatch('attendanceStatsChanged');

        $this->dispatch('notify', message: 'Walk-in member registered successfully!', type: 'success');
    }

    public function render()
    {
        return view('livewire.add-walk-in-modal');
    }
}
