<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Form;
use App\Models\User;
use App\Notifications\FormResponsesShared;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Lets a manager share one form's responses with people by email. Someone without an
 * account gets one that can only see shared responses (see ConfineFormViewers).
 */
class FormViewerController extends Controller
{
    public function store(Request $request, Event $event, Form $form): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($form->event_id === $event->id, 404);

        $validated = $request->validate([
            'viewer_name' => ['nullable', 'string', 'max:255'],
            'viewer_email' => ['required', 'email', 'max:255'],
        ]);
        $email = strtolower(trim($validated['viewer_email']));

        $user = User::whereRaw('lower(email) = ?', [$email])->first();
        if ($user && $form->viewers()->whereKey($user->id)->exists()) {
            return back()->with('success', $email.' can already see these responses.');
        }

        $temporaryPassword = null;
        if (! $user) {
            $temporaryPassword = Str::password(16);
            $user = User::create([
                'name' => trim((string) ($validated['viewer_name'] ?? '')) ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Hash::make($temporaryPassword),
                'category' => 'staff',
                'role' => 'form_viewer',
                'company_id' => $event->company_id,
                'email_verified_at' => now(),
                'must_change_password' => true,
            ]);
            $user->assignRole(Role::findOrCreate('form_viewer'));
        }

        $form->viewers()->attach($user->id, ['invited_by' => $request->user()->id]);
        $user->notify(new FormResponsesShared($form, $temporaryPassword));

        return back()->with('success', "Responses shared with {$email}. They've been emailed how to sign in.");
    }

    public function destroy(Event $event, Form $form, User $viewer): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($form->event_id === $event->id, 404);

        $form->viewers()->detach($viewer->id);

        return back()->with('success', $viewer->email.' can no longer see these responses.');
    }
}
