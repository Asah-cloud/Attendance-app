<x-app-layout>
    <x-slot name="header">Account Settings</x-slot>

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
