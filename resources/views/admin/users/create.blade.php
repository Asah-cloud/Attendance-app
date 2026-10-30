<x-app-layout>
    <x-slot name="header">Add Team Member</x-slot>

    <div class="mx-auto max-w-2xl">
        <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-xs font-extrabold uppercase tracking-wider text-blue-600">Team & access</p>
                <h2 class="mt-1 text-2xl font-black">Add a team member</h2>
                <p class="mt-2 text-sm text-slate-500">Create a staff login for attendance or food operations.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.register.store') }}" class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            @csrf
            <div class="grid gap-5 sm:grid-cols-2">
                <div><x-input-label for="name" :value="__('Full name')" /><x-text-input id="name" class="mt-1 block w-full" type="text" name="name" :value="old('name')" required autofocus /><x-input-error :messages="$errors->get('name')" class="mt-1" /></div>
                <div><x-input-label for="email" :value="__('Work email')" /><x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required /><x-input-error :messages="$errors->get('email')" class="mt-1" /></div>

                <div>
                    <x-input-label for="company_id" :value="__('Company')" />
                    @if($isAdmin)
                        <select id="company_id" name="company_id" class="mt-1 block w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500" required>
                            <option value="">Select a company&hellip;</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <x-text-input class="mt-1 block w-full bg-slate-50" type="text" value="{{ $companies->first()?->name }}" disabled />
                        <input type="hidden" name="company_id" value="{{ $companies->first()?->id }}">
                    @endif
                    <x-input-error :messages="$errors->get('company_id')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="role" :value="__('Role')" />
<select id="role" name="role" class="mt-1 block w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500" required>
                            @foreach($assignableRoles as $roleOption)
                                <option value="{{ $roleOption }}" {{ old('role', 'usher') === $roleOption ? 'selected' : '' }}>{{ str($roleOption)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>

                    <x-input-error :messages="$errors->get('role')" class="mt-1" />
                </div>
            </div>
            <p class="text-xs leading-5 text-slate-500">New staff are automatically assigned on every event currently in the selected company. You can fine-tune which events they can access afterwards from Edit Member.</p>
            <fieldset data-sign-in-choice>
                <legend class="text-sm font-bold text-slate-700">How they sign in</legend>
                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer gap-3 rounded-2xl border border-slate-200 p-4 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                        <input type="radio" name="sign_in" value="code" class="mt-1 text-blue-600 focus:ring-blue-500" {{ old('sign_in', 'code') === 'code' ? 'checked' : '' }}>
                        <span><span class="block text-sm font-extrabold text-slate-800">Send a sign-in code</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-500">No password. We email a 6-digit code (lasts 7 days); they type it on the sign-in page. Later they ask for a new code each time. For ushers and audit staff.</span></span>
                    </label>
                    <label class="flex cursor-pointer gap-3 rounded-2xl border border-slate-200 p-4 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                        <input type="radio" name="sign_in" value="password" class="mt-1 text-blue-600 focus:ring-blue-500" {{ old('sign_in') === 'password' ? 'checked' : '' }}>
                        <span><span class="block text-sm font-extrabold text-slate-800">Temporary password</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-500">We email a temporary password; they choose their own the first time they sign in. Managers and admins always use this.</span></span>
                    </label>
                </div>
                <x-input-error :messages="$errors->get('sign_in')" class="mt-1" />
            </fieldset>
            <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:justify-end">
                <a href="{{ route('admin.users.index') }}" class="rounded-xl border border-slate-200 px-5 py-3 text-center text-sm font-extrabold text-slate-600 hover:bg-slate-50">Cancel</a>
                <button class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-extrabold text-white shadow-lg shadow-blue-200 hover:bg-blue-700">Create account</button>
            </div>
        </form>
    </div>

    <script>
        // Managers and admins always sign in with a password.
        (() => {
            const role = document.getElementById('role');
            const choice = (value) => document.querySelector(`[data-sign-in-choice] input[value="${value}"]`);
            const sync = () => {
                const staff = ['usher', 'audit_head', 'audit_staff'].includes(role.value);
                choice('code').disabled = !staff;
                if (!staff) choice('password').checked = true;
            };
            role.addEventListener('change', sync);
            sync();
        })();
    </script>
</x-app-layout>
