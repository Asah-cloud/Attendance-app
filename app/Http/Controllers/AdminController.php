<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $currentUser = Auth::user();

        $query = User::with(['roles', 'company']);

        if (! $currentUser->hasRole('admin')) {
            $query->where('company_id', $currentUser->company_id);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate(20)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function edit(User $user)
    {
        $this->authorize('manage', $user);
        $currentUser = Auth::user();
        $roles = Role::all();

        if (! $currentUser->hasRole('admin')) {
            // Use a fresh query builder instance to avoid calling any custom static where()
            // Standardize company retrieval similarly
            $companies = (new Company)->newQuery()->where('id', $currentUser->company_id)->get();
        } else {
            $companies = Company::all();
        }

        $events = Event::query()
            ->when(! $currentUser->hasRole('admin'), fn ($query) => $query->where('company_id', $currentUser->company_id))
            ->orderByDesc('event_date')
            ->get();
        $user->load('events');

        return view('admin.users.edit', compact('user', 'roles', 'companies', 'events'));
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('manage', $user);
        $currentUser = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'role' => ['required', 'exists:roles,name'],
            'company_id' => 'nullable|exists:companies,id',
            'event_ids' => ['nullable', 'array'],
            'event_ids.*' => ['integer', 'exists:events,id'],
        ]);

        if (! $currentUser->hasRole('admin')) {
            if ($request->input('company_id') != $currentUser->company_id
                || ! in_array($request->input('role'), ['usher', 'audit_head', 'audit_staff'], true)) {
                abort(403);
            }
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->company_id = $request->company_id;
        $user->role = $request->role;
        $user->save();

        $user->syncRoles($request->role);

        // An usher can only ever be staffed on events belonging to their own company,
        // regardless of which company the acting admin happens to be scoped to.
        $eventIds = Event::query()
            ->whereIn('id', $request->input('event_ids', []))
            ->where('company_id', $user->company_id)
            ->pluck('id');
        $user->events()->sync(in_array($request->role, ['usher', 'audit_head', 'audit_staff'], true) ? $eventIds : []);
        if ($request->role !== 'audit_staff') {
            $user->mealStations()->detach();
        } else {
            $user->mealStations()->detach($user->mealStations()->whereNotIn('event_id', $eventIds)->pluck('meal_stations.id'));
        }

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully!');
    }

    public function destroy(User $user)
    {
        $this->authorize('manage', $user);

        if (Auth::id() === $user->id) {
            return back()->with('error', 'You cannot delete yourself.');
        }

        // Fix: Changed back to strict destroy method to resolve the argument error
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User removed.');
    }
}
