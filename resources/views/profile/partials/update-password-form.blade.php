<section class="bg-white p-6 sm:p-8 rounded-2xl border border-gray-100 shadow-sm">
    <header class="flex items-center gap-4 mb-8">
        <div class="p-3 bg-blue-50 rounded-xl text-blue-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
            </svg>
        </div>
        <div>
            <h2 class="text-lg font-black text-gray-900 uppercase tracking-tight">
                {{ __('Update Password') }}
            </h2>
            <p class="mt-1 text-sm text-gray-500 font-medium">
                {{ __('Ensure your account is using a long, random password to stay secure.') }}
            </p>
        </div>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="space-y-6 max-w-xl">
        @csrf
        @method('put')

        <div class="space-y-2">
            <x-input-label for="update_password_current_password" :value="__('Current Password')" class="text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" 
                class="block w-full px-4 py-3 bg-gray-50 border-gray-100 rounded-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all outline-none" 
                autocomplete="current-password" 
                placeholder="••••••••" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2 text-xs font-bold" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-2">
                <x-input-label for="update_password_password" :value="__('New Password')" class="text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1" />
                <x-text-input id="update_password_password" name="password" type="password" 
                    class="block w-full px-4 py-3 bg-gray-50 border-gray-100 rounded-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all outline-none" 
                    autocomplete="new-password" 
                    placeholder="New password" />
                <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2 text-xs font-bold" />
            </div>

            <div class="space-y-2">
                <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" class="text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1" />
                <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" 
                    class="block w-full px-4 py-3 bg-gray-50 border-gray-100 rounded-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all outline-none" 
                    autocomplete="new-password" 
                    placeholder="Confirm password" />
                <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2 text-xs font-bold" />
            </div>
        </div>

        <div class="flex items-center gap-4 pt-2">
            <x-primary-button class="px-8 py-3 rounded-xl font-black text-xs uppercase tracking-widest shadow-xl shadow-blue-100 transition-all hover:-translate-y-0.5 active:scale-95">
                {{ __('Update Security') }}
            </x-primary-button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-x-4"
                    x-transition:enter-end="opacity-100 translate-x-0"
                    x-init="setTimeout(() => show = false, 3000)"
                    class="flex items-center gap-2 text-sm font-black text-green-600 uppercase tracking-tighter"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    {{ __('Password Secured') }}
                </p>
            @endif
        </div>
    </form>
</section>