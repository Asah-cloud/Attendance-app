<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SignInCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    /**
     * Update the user's password. Staff who sign in with a code never had a password to give,
     * so they confirm with a code emailed to them instead.
     */
    public function update(Request $request, SignInCode $codes): RedirectResponse
    {
        $withCode = $request->user()->signs_in_with_code;
        $validated = $request->validateWithBag('updatePassword', [
            ...($withCode ? ['code' => ['required', 'string']] : ['current_password' => ['required', 'current_password']]),
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        if ($withCode && ! $codes->check($request->user(), $validated['code'])) {
            throw ValidationException::withMessages(['code' => 'That code is wrong or has expired. Email yourself a new one.'])->errorBag('updatePassword');
        }

        $request->user()->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        return back()->with('status', 'password-updated');
    }

    /** Email a code-sign-in user the code to set their password with. */
    public function sendCode(Request $request, SignInCode $codes): RedirectResponse
    {
        abort_unless($request->user()->signs_in_with_code, 404);
        $codes->sendForPassword($request->user());

        return back()->with('status', 'password-code-sent');
    }
}
