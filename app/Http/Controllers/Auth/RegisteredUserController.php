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

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'category' => 'regular',
        'role' => 'regular',
    ]);

    $user->assignRole('regular');

    event(new Registered($user));

    // CHECK: If you are already logged in as an Admin, don't log in as the new user
    if (Auth::check() && Auth::user()->hasRole('admin')) {
        return redirect()->route('admin.users.index')
            ->with('status', 'New user registered successfully!');
    }

    // Otherwise, log in the new user (normal guest registration)
    Auth::login($user);

    return redirect(route('dashboard', absolute: false));
}
}
