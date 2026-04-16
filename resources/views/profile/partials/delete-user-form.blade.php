<section class="space-y-6">
    <div class="bg-red-50/50 border border-red-100 rounded-2xl p-6 sm:p-8">
        <header class="flex items-start gap-4">
            <div class="p-3 bg-red-100 rounded-xl text-red-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </div>
            <div>
                <h2 class="text-lg font-black text-gray-900 uppercase tracking-tight">
                    {{ __('Danger Zone') }}
                </h2>

                <p class="mt-1 text-sm text-gray-600 font-medium leading-relaxed">
                    {{ __('Once your account is deleted, all of its resources and data will be permanently removed. This action cannot be undone.') }}
                </p>
            </div>
        </header>

        <div class="mt-6">
            <x-danger-button
                class="px-8 py-3 rounded-xl font-black text-xs uppercase tracking-[0.1em] shadow-lg shadow-red-200 transition-all hover:-translate-y-0.5"
                x-data=""
                x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
            >
                {{ __('Delete Account') }}
            </x-danger-button>
        </div>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-8">
            @csrf
            @method('delete')

            <div class="mb-6 text-center">
                <div class="inline-flex p-4 bg-red-100 text-red-600 rounded-full mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-black text-gray-900 tracking-tight">
                    {{ __('Final Confirmation') }}
                </h2>

                <p class="mt-2 text-sm text-gray-500 font-medium">
                    {{ __('Please enter your password to confirm you want to permanently delete your account.') }}
                </p>
            </div>

            <div class="max-w-md mx-auto">
                <div class="relative group">
                    <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />

                    <x-text-input
                        id="password"
                        name="password"
                        type="password"
                        class="block w-full px-4 py-4 bg-gray-50 border-gray-200 rounded-2xl focus:ring-4 focus:ring-red-500/10 focus:border-red-500 transition-all outline-none text-center"
                        placeholder="{{ __('Enter Password to Confirm') }}"
                    />
                </div>

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-3 text-center" />
            </div>

            <div class="mt-8 flex flex-col sm:flex-row justify-center gap-4">
                <button type="button" x-on:click="$dispatch('close')" class="px-8 py-3 bg-gray-100 text-gray-600 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-gray-200 transition-all order-2 sm:order-1">
                    {{ __('Keep My Account') }}
                </button>

                <x-danger-button class="px-8 py-3 rounded-xl font-black text-xs uppercase tracking-widest shadow-xl shadow-red-200 order-1 sm:order-2">
                    {{ __('Confirm Deletion') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>