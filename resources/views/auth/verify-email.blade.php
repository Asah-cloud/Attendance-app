<x-guest-layout>
    {{-- Verification Header --}}
    <div class="mb-8 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-emerald-50 rounded-3xl mb-4 shadow-sm border border-emerald-100">
            <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
            </svg>
        </div>
        <h2 class="text-xl font-black text-gray-900 uppercase tracking-tight">Verify Inbox</h2>
        <p class="mt-2 text-[10px] font-black text-emerald-500 uppercase tracking-[0.3em]">Activation Required</p>
    </div>

    <div class="mb-6 bg-gray-50/50 p-6 rounded-[2rem] border border-gray-100 shadow-inner">
        <p class="text-[11px] font-medium text-gray-600 uppercase tracking-widest leading-relaxed text-center">
            {{ __('Thanks for signing up! Please verify your email by clicking the link we just sent. If you didn\'t get it, we can send another.') }}
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-6 p-4 bg-emerald-600 rounded-2xl shadow-lg shadow-emerald-100 animate-bounce-short">
            <p class="text-[10px] font-black text-white uppercase tracking-widest text-center">
                {{ __('A new link has been dispatched to your email.') }}
            </p>
        </div>
    @endif

    <div class="flex flex-col gap-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button class="w-full justify-center py-4 bg-emerald-600 hover:bg-emerald-700 shadow-emerald-100">
                {{ __('Resend Dispatch') }}
            </x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="text-center">
            @csrf
            <button type="submit" class="text-[10px] font-black text-gray-400 hover:text-red-500 uppercase tracking-[0.2em] transition-colors">
                {{ __('Cancel & Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>