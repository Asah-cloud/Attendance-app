<x-guest-layout>
    {{-- Welcome Header --}}
    <div class="mb-8 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-600 rounded-3xl mb-4 shadow-xl shadow-blue-100">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
            </svg>
        </div>
        <h2 class="text-2xl font-black text-gray-900 uppercase tracking-tighter">System Access</h2>
        <p class="mt-1 text-[10px] font-black text-blue-500 uppercase tracking-[0.3em]">Authorized Personnel Only</p>
    </div>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div class="space-y-4 bg-gray-50/50 p-6 rounded-[2rem] border border-gray-100 shadow-inner">
            <div>
                <x-input-label for="email" :value="__('Operator Email')" />
                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus placeholder="admin@system.com" />
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <x-input-label for="password" :value="__('Access Key')" />
                    @if (Route::has('password.request'))
                        <a class="text-[9px] font-black text-blue-600 uppercase tracking-widest hover:text-blue-800 transition" href="{{ route('password.request') }}">
                            {{ __('Forgot?') }}
                        </a>
                    @endif
                </div>
                <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required placeholder="••••••••" />
                <x-input-error :messages="$errors->get('password')" class="mt-1" />
            </div>
        </div>

        <div class="flex items-center justify-between px-2">
            <label for="remember_me" class="inline-flex items-center cursor-pointer group">
                <input id="remember_me" type="checkbox" class="rounded-lg border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500/20 w-5 h-5 transition-all group-hover:border-blue-400" name="remember">
                <span class="ms-3 text-[10px] font-black text-gray-400 uppercase tracking-widest group-hover:text-gray-600 transition">{{ __('Stay Logged In') }}</span>
            </label>
        </div>

        <div class="pt-2 space-y-4">
            <x-primary-button class="w-full justify-center py-4 text-sm">
                {{ __('Open Console') }}
            </x-primary-button>
            
            <div class="flex items-center justify-center gap-2">
                <span class="text-[10px] font-bold text-gray-300 uppercase tracking-widest">New Operator?</span>
                <span class="text-[10px] font-black text-gray-500 uppercase tracking-widest">
                    {{ __('Need access? Contact your event organizer.') }}
                </span>
            </div>
        </div>
    </form>
</x-guest-layout>
