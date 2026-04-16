<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class MemberDirectory extends Component
{
    use WithPagination;

    // This variable links to wire:model.live="search" in your HTML
    public $search = '';

    /**
     * This resets the page to 1 whenever the search changes.
     * Otherwise, if you are on page 5 and search, you might see nothing.
     */
    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
{
    $query = User::query()
        ->with('roles')
        ->role(['admin', 'regular']);

    // Split search into individual words (e.g., "John Doe" becomes ["John", "Doe"])
    $keywords = collect(explode(' ', $this->search))->filter();

    if ($keywords->isNotEmpty()) {
        $query->where(function ($q) use ($keywords) {
            foreach ($keywords as $word) {
                $q->where(function ($inner) use ($word) {
                    // 'ilike' is case-insensitive in PostgreSQL. 
                    // For MySQL, 'like' is usually case-insensitive by default,
                    // but we use lowercase comparison to be 100% sure.
                    $inner->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($word) . '%'])
                          ->orWhereRaw('LOWER(email) LIKE ?', ['%' . strtolower($word) . '%']);
                });
            }
        });
    }

    return view('livewire.member-directory', [
        'users' => $query->latest()->paginate(10)
    ]);
}
}