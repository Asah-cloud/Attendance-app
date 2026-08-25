<?php

namespace App\Http\Controllers;

use App\Models\PasswordResetAudit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AdminPasswordResetController extends Controller
{
    public function sendLink(Request $request, User $user): RedirectResponse
    {
        $this->authorizeReset($request, $user);
        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->with('error', __($status));
        }

        $this->audit($request, $user, 'email_link');

        return back()->with('success', "A secure password reset link was sent to {$user->email}.");
    }

    public function temporaryPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorizeReset($request, $user);
        $temporaryPassword = Str::password(16);

        $user->forceFill([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
            'remember_token' => Str::random(60),
        ])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        $this->audit($request, $user, 'temporary_password');

        return back()
            ->with('success', 'Temporary password generated. It will be shown only once.')
            ->with('temporary_password', $temporaryPassword)
            ->with('temporary_password_user', $user->name);
    }

    private function authorizeReset(Request $request, User $user): void
    {
        $this->authorize('manage', $user);
        abort_if($request->user()->is($user) || $user->hasRole('admin'), 403);
    }

    private function audit(Request $request, User $user, string $method): void
    {
        PasswordResetAudit::create([
            'user_id' => $user->id,
            'initiated_by' => $request->user()->id,
            'method' => $method,
            'ip_address' => $request->ip(),
        ]);
    }
}
