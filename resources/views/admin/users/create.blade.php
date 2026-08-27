<x-app-layout>
    <x-slot name="header">Add Team Member</x-slot>

    <div class="mx-auto max-w-2xl">
        <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-xs font-extrabold uppercase tracking-wider text-blue-600">Team & access</p>
                <h2 class="mt-1 text-2xl font-black">Add an usher</h2>
                <p class="mt-2 text-sm text-slate-500">Create a staff login for attendance and QR scanning.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.register.store') }}" class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            @csrf
            <div class="grid gap-5 sm:grid-cols-2">
                <div><x-input-label for="name" :value="__('Full name')" /><x-text-input id="name" class="mt-1 block w-full" type="text" name="name" :value="old('name')" required autofocus /><x-input-error :messages="$errors->get('name')" class="mt-1" /></div>
                <div><x-input-label for="email" :value="__('Work email')" /><x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required /><x-input-error :messages="$errors->get('email')" class="mt-1" /></div>
                <div><x-input-label for="password" :value="__('Temporary password')" /><x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required /><x-input-error :messages="$errors->get('password')" class="mt-1" /></div>
                <div><x-input-label for="password_confirmation" :value="__('Confirm password')" /><x-text-input id="password_confirmation" class="mt-1 block w-full" type="password" name="password_confirmation" required /><x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" /></div>
            </div>
            <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:justify-end">
                <a href="{{ route('admin.users.index') }}" class="rounded-xl border border-slate-200 px-5 py-3 text-center text-sm font-extrabold text-slate-600 hover:bg-slate-50">Cancel</a>
                <button class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-extrabold text-white shadow-lg shadow-blue-200 hover:bg-blue-700">Create usher account</button>
            </div>
        </form>
    </div>
</x-app-layout>
