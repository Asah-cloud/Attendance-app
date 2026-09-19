<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class MealOverviewStats extends Component
{
    public Event $event;

    public function render()
    {
        Gate::authorize('viewMeals', $this->event);

        return view('livewire.meal-overview-stats', [
            'confirmed' => $this->event->registrations()->where('status', EventRegistration::STATUS_CONFIRMED)->count(),
            'checkedIn' => $this->event->attendances()->distinct()->count('participant_id'),
        ]);
    }
}
