<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-white rounded-lg shadow-sm">
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
            </div>
            <h2 class="font-black text-xl text-gray-900 uppercase tracking-tight">
                {{ __('Account Settings') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12 bg-gray-50/50 min-h-screen">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-8">
            
            {{-- Profile Details Section --}}
            <div class="animate-in fade-in slide-in-from-bottom-4 duration-500">
                @include('profile.partials.update-profile-information-form')
            </div>

            {{-- Password Security Section --}}
            <div class="animate-in fade-in slide-in-from-bottom-4 duration-700">
                @include('profile.partials.update-password-form')
            </div>

            {{-- Danger Zone Section --}}
            <div class="animate-in fade-in slide-in-from-bottom-4 duration-1000">
                @include('profile.partials.delete-user-form')
            </div>

            {{-- Footer Note --}}
            <div class="text-center pt-4 pb-8">
                <p class="text-[10px] font-black text-gray-300 uppercase tracking-[0.3em]">
                    App Version 1.0.4 • Attendance System
                </p>
            </div>
        </div>
    </div>
</x-app-layout>