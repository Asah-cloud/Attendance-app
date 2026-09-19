<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\MealDistribution;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class MealRecentCollections extends Component
{
    public Event $event;

    public MealDistribution $meal;

    public function render()
    {
        Gate::authorize('viewMeals', $this->event);
        abort_unless($this->meal->event_id === $this->event->id, 404);

        $collections = $this->meal->collections()
            ->when(auth()->user()->isAuditStaff(), fn ($query) => $query->whereIn(
                'meal_station_id',
                $this->event->mealStations()->whereHas('staff', fn ($staff) => $staff->whereKey(auth()->id()))->select('meal_stations.id')
            ))
            ->with(['participant', 'station'])
            ->latest('collected_at')
            ->limit(10)
            ->get();

        return view('livewire.meal-recent-collections', compact('collections'));
    }
}
