<x-guest-layout>
    <div class="mb-8 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-600 rounded-3xl mb-4 shadow-xl shadow-blue-100">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
            </svg>
        </div>
        <h2 class="text-2xl font-black text-gray-900 uppercase tracking-tighter">Sign in with a code</h2>
        <p class="mt-2 text-xs text-gray-500">For team members added without a password.</p>
    </div>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    @if($email === '')
        {{-- Step 1: which email to send the code to. --}}
        <form method="POST" action="{{ route('login.code.send') }}" class="space-y-5" data-code-request>
            @csrf
            <div class="bg-gray-50/50 p-6 rounded-[2rem] border border-gray-100 shadow-inner">
                <x-input-label for="email" :value="__('Your email')" />
                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus placeholder="name@company.com" />
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
            </div>
            <x-primary-button class="w-full justify-center py-4 text-sm">{{ __('Email me a code') }}</x-primary-button>
        </form>
    @else
        {{-- Step 2: type the code from the email. --}}
        <form method="POST" action="{{ route('login.code.store') }}" class="space-y-5" data-code-entry>
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">
            <div class="bg-gray-50/50 p-6 rounded-[2rem] border border-gray-100 shadow-inner">
                <p class="text-xs text-gray-500">Signing in as <span class="font-bold text-gray-800">{{ $email }}</span>
                    · <a href="{{ route('login.code') }}" class="font-bold text-blue-600 hover:text-blue-800">change</a></p>
                <x-input-label for="code" class="mt-4" :value="__('Code from the email')" />
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus
                    placeholder="123 456"
                    class="mt-1 block w-full rounded-xl border-gray-300 text-center text-2xl font-black tracking-[0.4em] focus:border-blue-500 focus:ring-blue-500">
                <x-input-error :messages="$errors->get('code')" class="mt-1" />
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
            </div>

            <label for="remember_me" class="inline-flex items-center cursor-pointer px-2">
                <input id="remember_me" type="checkbox" name="remember" class="rounded-lg border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500/20 w-5 h-5">
                <span class="ms-3 text-xs font-bold text-gray-500">Keep me signed in on this device for 30 days</span>
            </label>

            <x-primary-button class="w-full justify-center py-4 text-sm">{{ __('Sign in') }}</x-primary-button>
        </form>

        <form method="POST" action="{{ route('login.code.send') }}" class="mt-4 text-center">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">
            <button class="text-xs font-bold text-blue-600 hover:text-blue-800">No code, or it's out of date? Email me a new one</button>
        </form>
    @endif

    <p class="mt-6 text-center text-xs text-gray-400">
        Have a password? <a href="{{ route('login') }}" class="font-bold text-blue-600 hover:text-blue-800">Sign in with your password</a>
    </p>
</x-guest-layout>
