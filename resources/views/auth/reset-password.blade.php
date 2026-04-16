<x-guest-layout>
    {{-- Recovery Header --}}
    <div class="mb-8 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-amber-50 rounded-3xl mb-4 shadow-sm border border-amber-100">
            <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
            </svg>
        </div>
        <h2 class="text-xl font-black text-gray-900 uppercase tracking-tight">Update Access Key</h2>
        <p class="mt-2 text-[10px] font-black text-amber-500 uppercase tracking-[0.3em]">Security Protocol Active</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-6">
        @csrf

        <input type="hidden" name="token" value="{{ request()->route('token') }}">

        <div class="bg-gray-50/50 p-6 rounded-[2rem] border border-gray-100 shadow-inner">
            <x-input-label for="email" :value="__('Verify Email')" />
            <x-text-input id="email" class="block mt-1 w-full opacity-75 cursor-not-allowed bg-gray-100" type="email" name="email" :value="old('email', $request->email)" required readonly />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="bg-blue-50/30 p-6 rounded-[2rem] border border-blue-100/50 shadow-inner space-y-4">
            <div>
                <x-input-label for="password" :value="__('New Password')" />
                <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required placeholder="••••••••" autofocus />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password_confirmation" :value="__('Repeat New Password')" />
                <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required placeholder="••••••••" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        <div class="pt-2">
            <x-primary-button class="w-full justify-center py-4 bg-amber-600 hover:bg-amber-700 shadow-amber-100">
                {{ __('Confirm New Password') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>