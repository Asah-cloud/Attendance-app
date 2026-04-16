<x-guest-layout>
    {{-- Onboarding Header --}}
    <div class="mb-8 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-purple-50 rounded-3xl mb-4 shadow-sm border border-purple-100">
            <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
            </svg>
        </div>
        <h2 class="text-xl font-black text-gray-900 uppercase tracking-tight">Operator Setup</h2>
        <p class="mt-2 text-[10px] font-black text-purple-500 uppercase tracking-[0.3em]">Initialize New Account</p>
    </div>

    {{-- Change this line --}}
<form method="POST" action="{{ Auth::check() ? route('admin.register.store') : route('register') }}" class="space-y-6">
        @csrf

        <div class="bg-gray-50/50 p-6 rounded-[2rem] border border-gray-100 shadow-inner space-y-4">
            <div>
                <x-input-label for="name" :value="__('Full Name')" />
                <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus placeholder="John Doe" />
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="email" :value="__('Work Email')" />
                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required placeholder="name@company.com" />
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
            </div>
        </div>

        <div class="bg-blue-50/30 p-6 rounded-[2rem] border border-blue-100/50 shadow-inner space-y-4">
            <div>
                <x-input-label for="password" :value="__('Create Password')" />
                <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required placeholder="••••••••" />
                <x-input-error :messages="$errors->get('password')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="password_confirmation" :value="__('Verify Password')" />
                <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required placeholder="••••••••" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
            </div>
        </div>

        <div class="flex flex-col gap-4">
            <x-primary-button class="w-full justify-center py-4 bg-purple-600 hover:bg-purple-700 shadow-purple-100">
                {{ __('Finalize Registration') }}
            </x-primary-button>
            
            <a href="{{ route('login') }}" class="text-center text-[10px] font-black text-gray-400 hover:text-gray-600 uppercase tracking-[0.2em] transition">
                {{ __('Back to System Login') }}
            </a>
        </div>
    </form>
</x-guest-layout>