<section class="bg-white p-6 sm:p-8 rounded-2xl border border-gray-100 shadow-sm">
    <header class="flex items-center gap-4 mb-8">
        <div class="p-3 bg-purple-50 rounded-xl text-purple-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
        </div>
        <div>
            <h2 class="text-lg font-black text-gray-900 uppercase tracking-tight">
                {{ __('Profile Information') }}
            </h2>
            <p class="mt-1 text-sm text-gray-500 font-medium">
                {{ __("Update your account's identity and communication details.") }}
            </p>
        </div>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6 max-w-xl">
        @csrf
        @method('patch')

        {{-- Name Input --}}
        <div class="space-y-2">
            <x-input-label for="name" :value="__('Full Name')" class="text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1" />
            <div class="relative group">
                <x-text-input id="name" name="name" type="text" 
                    class="block w-full px-4 py-3 bg-gray-50 border-gray-100 rounded-xl focus:ring-4 focus:ring-purple-500/10 focus:border-purple-500 transition-all outline-none" 
                    :value="old('name', $user->name)" required autofocus autocomplete="name" />
            </div>
            <x-input-error class="mt-2 text-xs font-bold" :messages="$errors->get('name')" />
        </div>

        {{-- Email Input --}}
        <div class="space-y-2">
            <x-input-label for="email" :value="__('Email Address')" class="text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1" />
            <div class="relative group">
                <x-text-input id="email" name="email" type="email" 
                    class="block w-full px-4 py-3 bg-gray-50 border-gray-100 rounded-xl focus:ring-4 focus:ring-purple-500/10 focus:border-purple-500 transition-all outline-none" 
                    :value="old('email', $user->email)" required autocomplete="username" />
            </div>
            <x-input-error class="mt-2 text-xs font-bold" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-4 p-4 bg-amber-50 rounded-xl border border-amber-100">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <p class="text-xs font-bold text-amber-800 uppercase tracking-tight">
                            {{ __('Your email address is unverified.') }}
                        </p>
                    </div>

                    <button form="send-verification" class="mt-2 text-xs font-black text-amber-600 hover:text-amber-700 underline underline-offset-4 decoration-amber-200 uppercase tracking-widest">
                        {{ __('Click to resend verification link') }}
                    </button>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-3 font-black text-[10px] text-green-600 uppercase tracking-widest flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            {{ __('Verification Link Sent') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4 pt-4">
            <x-primary-button class="px-8 py-3 bg-gray-900 text-white rounded-xl font-black text-xs uppercase tracking-widest shadow-xl shadow-gray-200 hover:bg-black transition-all hover:-translate-y-0.5 active:scale-95">
                {{ __('Update Profile') }}
            </x-primary-button>

            @if (session('status') === 'profile-updated')
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
                    {{ __('Details Saved') }}
                </p>
            @endif
        </div>
    </form>
</section>