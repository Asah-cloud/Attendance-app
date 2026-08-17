<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $currentUser = Auth::user();

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'category' => 'staff',
            'role' => 'usher',
            // If logged-in user is not a super admin, automatically assign their company id
            'company_id' => ($currentUser && ! $currentUser->hasRole('admin')) ? $currentUser->company_id : null,
        ]);

        $user->assignRole(Role::findOrCreate('usher'));

        event(new Registered($user));

        // If an authorized manager or admin is creating this person, keep them logged in and redirect
        if (Auth::check() && ($currentUser->hasRole('admin') || $currentUser->hasRole('manager'))) {
            return redirect()->route('admin.users.index')
                ->with('success', 'New user registered successfully to your company!');
        }

        // Otherwise, log in the new user (normal guest registration workflow)
        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
