<x-guest-layout>
    {{-- Header with Icon --}}
    <div class="mb-8 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-50 rounded-3xl mb-4 shadow-sm border border-blue-100">
            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
            </svg>
        </div>
        <h2 class="text-xl font-black text-gray-900 uppercase tracking-tight">Recovery Center</h2>
        <p class="mt-3 text-[11px] font-medium text-gray-500 uppercase tracking-widest leading-relaxed px-2">
            {{ __('Forgot your password? Enter your email and we will send a reset link to your inbox.') }}
        </p>
    </div>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-6">
        @csrf

        <div class="bg-gray-50/50 p-6 rounded-[2rem] border border-gray-100 shadow-inner">
            <x-input-label for="email" :value="__('Registered Email')" />
            
            <x-text-input id="email" 
                          class="block mt-1 w-full" 
                          type="email" 
                          name="email" 
                          :value="old('email')" 
                          placeholder="name@company.com"
                          required 
                          autofocus />
                          
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex flex-col gap-4">
            <x-primary-button class="w-full justify-center py-4">
                {{ __('Send Reset Link') }}
            </x-primary-button>
            
            <a href="{{ route('login') }}" class="text-center text-[10px] font-black text-blue-600 hover:text-blue-800 uppercase tracking-[0.2em] transition-colors">
                &larr; Return to Login
            </a>
        </div>
    </form>
</x-guest-layout>