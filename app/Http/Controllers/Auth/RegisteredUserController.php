<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Event;
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
     *
     * This route only ever runs behind the 'role:admin|manager' middleware group
     * (see routes/web.php), so the actor is always an authenticated admin or manager.
     */
    public function create(): View
    {
        $currentUser = Auth::user();
        $isAdmin = $currentUser->hasRole('admin');

        $companies = $isAdmin
            ? Company::orderBy('name')->get()
            : Company::query()->whereKey($currentUser->company_id)->get();

        // A manager may only ever create ushers in their own company; an admin
        // may create any staff role for any company.
        $assignableRoles = $isAdmin ? ['usher', 'manager', 'admin'] : ['usher'];

        return view('admin.users.create', compact('companies', 'assignableRoles', 'isAdmin'));
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
            'company_id' => ['nullable', 'exists:companies,id'],
            'role' => ['required', 'exists:roles,name'],
        ]);

        $currentUser = Auth::user();
        $isAdmin = $currentUser->hasRole('admin');

        // A manager can only ever create an usher within their own company,
        // regardless of what the submitted form fields say.
        $role = $isAdmin ? $request->string('role')->toString() : 'usher';
        $companyId = $isAdmin ? $request->input('company_id') : $currentUser->company_id;

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'category' => 'staff',
            'role' => $role,
            'company_id' => $companyId,
        ]);

        $user->assignRole(Role::findOrCreate($role));

        // An usher assigned to a company starts staffed on every event currently
        // in that company, so they aren't left with zero access until a manager
        // remembers to go pick events one by one. Future new events still need
        // an explicit assignment via Edit Member.
        if ($role === 'usher' && $companyId) {
            $user->events()->sync(Event::where('company_id', $companyId)->pluck('id'));
        }

        event(new Registered($user));

        return redirect()->route('admin.users.index')
            ->with('success', 'New user registered successfully!');
    }
}
