<x-guest-layout>
    <div class="mb-7 text-center">
        <p class="text-xs font-black uppercase tracking-[0.2em] text-amber-600">Security required</p>
        <h1 class="mt-3 text-2xl font-black text-gray-900">Create your own password</h1>
        <p class="mt-2 text-sm leading-6 text-gray-500">You signed in with a temporary password. Change it before continuing to your workspace.</p>
    </div>

    <form method="POST" action="{{ route('password.force.update') }}" class="space-y-5">
        @csrf @method('PUT')
        <div><x-input-label for="current_password" :value="__('Temporary password')" /><x-text-input id="current_password" class="mt-1 block w-full" type="password" name="current_password" required autofocus /><x-input-error :messages="$errors->get('current_password')" class="mt-1" /></div>
        <div><x-input-label for="password" :value="__('New password')" /><x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required /><x-input-error :messages="$errors->get('password')" class="mt-1" /></div>
        <div><x-input-label for="password_confirmation" :value="__('Confirm new password')" /><x-text-input id="password_confirmation" class="mt-1 block w-full" type="password" name="password_confirmation" required /></div>
        <x-primary-button class="w-full justify-center py-4">Change password and continue</x-primary-button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="w-full text-center text-xs font-extrabold text-gray-500 hover:text-gray-800">Log out instead</button></form>
</x-guest-layout>
