<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SignInCode;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Sign-in with an emailed code, for staff added without a password (users.signs_in_with_code).
 * The answers never say whether an email has an account.
 */
class CodeSignInController extends Controller
{
    /** How long "Keep me signed in" lasts after a code sign-in. */
    private const REMEMBER_MINUTES = 30 * 24 * 60;

    public function create(Request $request): View
    {
        return view('auth.login-code', [
            // Known once a code has been asked for, or from the link in the email: then the page asks for the code.
            'email' => (string) session('code_email', $request->query('email', '')),
        ]);
    }

    public function send(Request $request, SignInCode $codes): RedirectResponse
    {
        $email = Str::lower(trim($request->validate(['email' => ['required', 'string', 'email']])['email']));

        // A few codes per email every ten minutes, so nobody can flood someone's inbox.
        $key = 'sign-in-code-send:'.$email;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages([
                'email' => 'Too many codes asked for. Try again in '.ceil(RateLimiter::availableIn($key) / 60).' minutes, or use the last code you were sent.',
            ]);
        }
        RateLimiter::hit($key, 600);

        $user = User::where('email', $email)->where('signs_in_with_code', true)->first();
        if ($user) {
            $codes->send($user);
        }

        return redirect()->route('login.code')->with('code_email', $email)
            ->with('status', "If {$email} can sign in with a code, we've emailed one. It lasts ".SignInCode::LOGIN_MINUTES.' minutes.');
    }

    public function store(Request $request, SignInCode $codes): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email'], 'code' => ['required', 'string', 'max:20']]);
        $email = Str::lower(trim($data['email']));

        $key = 'sign-in-code:'.$email.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, SignInCode::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'code' => 'Too many tries. Ask for a new code in '.ceil(RateLimiter::availableIn($key) / 60).' minutes.',
            ])->redirectTo(route('login.code', ['email' => $email]));
        }

        $user = User::where('email', $email)->first();
        if (! $user || ! $codes->check($user, $data['code'])) {
            RateLimiter::hit($key, 900);
            event(new Failed('web', $user, ['email' => $email]));

            throw ValidationException::withMessages([
                'code' => 'That code is wrong, already used or out of date. Check the latest email, or ask for a new code.',
            ])->redirectTo(route('login.code', ['email' => $email]));
        }

        RateLimiter::clear($key);
        $remember = $request->boolean('remember');
        if ($remember) {
            Auth::guard('web')->setRememberDuration(self::REMEMBER_MINUTES);
        }
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
