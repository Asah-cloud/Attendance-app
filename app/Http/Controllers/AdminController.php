<?php

namespace App\Http\Controllers;

use App\Models\User; // Don't forget to import the User model
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role; // Import the Role model if needed
use Illuminate\Support\Facades\Auth; // added import

class AdminController extends Controller
{
    // app/Http/Controllers/AdminController.php

public function index(Request $request)
{
    $search = $request->input('search');

    // 1. Filter only for users with the 'regular and 'admin'` role
    $query = User::role(['regular', 'admin'])->with('roles');

    // 2. Apply search if the Admin is looking for someone specific
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
    $roles = Role::all(); // Get all roles (admin, usher, regular)
    return view('admin.users.edit', compact('user', 'roles'));
}
    public function update(Request $request, User $user)
{
    $request->validate([
        'role' => 'required|exists:roles,name',
    ]);

    // Spatie method to replace old roles with the new one
    $user->syncRoles($request->role);

    return redirect()->route('admin.users.index')->with('success', 'User role updated!');
}
    public function destroy(User $user)
    {
        // Prevent admin from deleting themselves if they show up in the list
        if (Auth::id() === $user->id) {
            return back()->with('error', 'You cannot delete yourself.');
        }

        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'User removed.');
    }
}