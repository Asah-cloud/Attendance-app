<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Event;
use App\Models\User;
use App\Notifications\NewAccountCredentials;
use App\Services\SignInCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

        // A manager may create attendance and audit staff in their own company; an admin
        // may create any staff role for any company.
        $assignableRoles = $isAdmin ? ['usher', 'audit_head', 'audit_staff', 'manager', 'admin'] : ['usher', 'audit_head', 'audit_staff'];

        return view('admin.users.create', compact('companies', 'assignableRoles', 'isAdmin'));
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, SignInCode $codes): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'company_id' => ['nullable', 'exists:companies,id'],
            'role' => ['required', 'exists:roles,name'],
            'sign_in' => ['nullable', 'in:code,password'],
        ]);

        $currentUser = Auth::user();
        $isAdmin = $currentUser->hasRole('admin');

        // A manager can create only attendance and audit roles within their own company,
        // regardless of what the submitted form fields say.
        $role = $request->string('role')->toString();
        abort_unless($isAdmin || in_array($role, ['usher', 'audit_head', 'audit_staff'], true), 403);
        $companyId = $isAdmin ? $request->input('company_id') : $currentUser->company_id;

        // Staff roles can be added to sign in with an emailed code instead of a password;
        // managers and admins always get a password.
        $withCode = $request->input('sign_in') === 'code' && in_array($role, ['usher', 'audit_head', 'audit_staff'], true);
        if ($request->input('sign_in') === 'code' && ! $withCode) {
            throw ValidationException::withMessages(['sign_in' => 'Managers and admins sign in with a password.']);
        }

        // A code user never learns this password; it only fills the column.
        $temporaryPassword = Str::password(16);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($temporaryPassword),
            'category' => 'staff',
            'role' => $role,
            'company_id' => $companyId,
            'email_verified_at' => now(),
            'must_change_password' => ! $withCode,
            'signs_in_with_code' => $withCode,
        ]);

        $user->assignRole(Role::findOrCreate($role));

        // New staff assigned to a company start staffed on every event currently
        // in that company, so they aren't left with zero access until a manager
        // remembers to go pick events one by one. Future new events still need
        // an explicit assignment via Edit Member.
        if (in_array($role, ['usher', 'audit_head', 'audit_staff'], true) && $companyId) {
            $user->events()->sync(Event::where('company_id', $companyId)->pluck('id'));
        }

        if ($withCode) {
            $codes->send($user, invite: true);

            return redirect()->route('admin.users.index')
                ->with('success', "{$user->name} has been added. We've emailed {$user->email} a sign-in code; it lasts 7 days.");
        }

        $user->notify(new NewAccountCredentials($temporaryPassword));

        return redirect()->route('admin.users.index')
            ->with('success', "New user registered successfully! We've emailed {$user->email} their temporary password.");
    }
}
