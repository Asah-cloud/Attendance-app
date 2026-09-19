<?php

namespace App\Livewire;

use App\Models\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class RegistrationStats extends Component
{
    public Event $event;

    public function render()
    {
        Gate::authorize('view', $this->event);
        $counts = $this->event->registrations()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('livewire.registration-stats', [
            'total' => $counts->sum(),
            'confirmed' => (int) ($counts['confirmed'] ?? 0),
            'pending' => (int) ($counts['pending'] ?? 0),
            'waitlisted' => (int) ($counts['waitlisted'] ?? 0),
        ]);
    }
}
