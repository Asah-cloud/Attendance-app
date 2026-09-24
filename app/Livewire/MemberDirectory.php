<?php

namespace App\Livewire;

use App\Models\User;
use App\Support\Search;
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
        $query->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['usher', 'audit_head', 'audit_staff', 'manager', 'admin']));

        // 3. Multitenancy isolation check
        // If the logged-in user is NOT a Super Admin, restrict them to their company's users
        if (! $currentUser->hasRole('admin')) {
            $query->where('company_id', $currentUser->company_id);
        }

        Search::apply($query, $this->search, ['name', 'email'], ['phone']);

        return view('livewire.member-directory', [
            'users' => $query->latest()->paginate(10),
        ]);
    }
}
