<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
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

    public function searchNow(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $currentUser = Auth::user();

        // 1. Fetch users with roles and company relationships
        $query = User::with(['roles', 'company']);

        // 2. Filter out imported list ('member' role)
        // Only show administrative and management roles on this page
        $query->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['usher', 'manager', 'admin']));

        // 3. Multitenancy isolation check
        // If the logged-in user is NOT a Super Admin, restrict them to their company's users
        if (! $currentUser->hasRole('admin')) {
            $query->where('company_id', $currentUser->company_id);
        }

        // Split search into individual words (e.g., "John Doe" becomes ["John", "Doe"])
        $keywords = collect(explode(' ', $this->search))->filter();

        if ($keywords->isNotEmpty()) {
            $query->where(function ($q) use ($keywords) {
                foreach ($keywords as $word) {
                    $q->where(function ($inner) use ($word) {
                        // 'ilike' is case-insensitive in PostgreSQL.
                        // For MySQL, 'like' is usually case-insensitive by default,
                        // but we use lowercase comparison to be 100% sure.
                        $inner->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($word).'%'])
                            ->orWhereRaw('LOWER(email) LIKE ?', ['%'.strtolower($word).'%']);
                    });
                }
            });
        }

        return view('livewire.member-directory', [
            'users' => $query->latest()->paginate(10),
        ]);
    }
}
