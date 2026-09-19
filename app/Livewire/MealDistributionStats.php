<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MealDistribution;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class MealDistributionStats extends Component
{
    public Event $event;

    public MealDistribution $meal;

    public function render()
    {
        Gate::authorize('viewMeals', $this->event);
        abort_unless($this->meal->event_id === $this->event->id, 404);

        $issued = (int) $this->meal->collections()->sum('quantity');
        $people = $this->meal->collections()->count();
        $confirmed = $this->event->registrations()->where('status', EventRegistration::STATUS_CONFIRMED)->count();

        return view('livewire.meal-distribution-stats', compact('issued', 'people', 'confirmed'));
    }
}
