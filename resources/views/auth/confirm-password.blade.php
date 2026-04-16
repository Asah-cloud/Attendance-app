<x-guest-layout>
    {{-- Security Header --}}
    <div class="mb-8 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-50 rounded-3xl mb-4 shadow-sm">
            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
            </svg>
        </div>
        <h2 class="text-xl font-black text-gray-900 uppercase tracking-tight">Security Check</h2>
        <p class="mt-2 text-[11px] font-medium text-gray-500 uppercase tracking-widest px-4">
            {{ __('Please confirm your password to access this secure area.') }}
        </p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-6">
        @csrf

        <div class="bg-gray-50/50 p-6 rounded-[2rem] border border-gray-100 shadow-inner">
            <x-input-label for="password" :value="__('Master Password')" />

            <x-text-input id="password" 
                          class="block mt-1 w-full"
                          type="password"
                          name="password"
                          required 
                          placeholder="••••••••"
                          autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex flex-col gap-4">
            <x-primary-button class="w-full justify-center py-4">
                {{ __('Verify Identity') }}
            </x-primary-button>
            
            <p class="text-center text-[10px] font-black text-gray-300 uppercase tracking-[0.3em]">
                Attendance System v1.0 Secure Gate
            </p>
        </div>
    </form>
</x-guest-layout>