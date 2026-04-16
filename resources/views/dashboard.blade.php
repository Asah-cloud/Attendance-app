<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-blue-600 rounded-lg shadow-lg shadow-blue-200 text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                </svg>
            </div>
            <h2 class="font-black text-xl text-gray-900 uppercase tracking-tight">
                {{ __('Control Center') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12 bg-gray-50/50 min-h-screen">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            {{-- Welcome Message --}}
            <div class="mb-10 animate-in fade-in slide-in-from-top-4 duration-500">
                <h1 class="text-3xl font-black text-gray-900 tracking-tighter uppercase">
                    Welcome back, {{ Auth::user()->name }}
                </h1>
                <p class="text-sm text-gray-500 font-medium mt-1">Ready to manage today's attendance sessions?</p>
            </div>

            {{-- Command Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                
                {{-- Action: Manage Events --}}
                <a href="{{ route('events.index') }}" class="group bg-white p-8 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl hover:shadow-blue-50 transition-all hover:-translate-y-1">
                    <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2-2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <h3 class="font-black text-gray-900 uppercase tracking-tight text-lg leading-tight">Mark Attendance</h3>
                    <p class="text-xs text-gray-500 mt-2 font-medium">Select an active event to start scanning or manual entry.</p>
                </a>

                {{-- ADMIN ONLY: Manage Regulars --}}
                @role('admin')
                <a href="{{ route('admin.users.index') }}" class="group bg-white p-8 rounded-3xl border-2 border-blue-100 shadow-sm hover:shadow-xl hover:shadow-blue-100 transition-all hover:-translate-y-1">
                    <div class="w-14 h-14 bg-blue-600 text-white rounded-2xl flex items-center justify-center mb-6 group-hover:bg-gray-900 transition-colors">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </div>
                    <h3 class="font-black text-gray-900 uppercase tracking-tight text-lg leading-tight">Manage Regulars</h3>
                    <p class="text-xs text-gray-500 mt-2 font-medium">View and manage users who registered on the platform.</p>
                </a>
                @endrole

                {{-- Action: Settings --}}
                <a href="{{ route('profile.edit') }}" class="group bg-white p-8 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl hover:shadow-gray-100 transition-all hover:-translate-y-1">
                    <div class="w-14 h-14 bg-gray-50 text-gray-600 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-gray-900 group-hover:text-white transition-colors">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                    </div>
                    <h3 class="font-black text-gray-900 uppercase tracking-tight text-lg leading-tight">System Settings</h3>
                    <p class="text-xs text-gray-500 mt-2 font-medium">Update account credentials and system preferences.</p>
                </a>

            </div>

            {{-- Quick Info Note --}}
            <div class="mt-12 text-center">
                <p class="text-[10px] font-black text-gray-300 uppercase tracking-[0.4em]">
                    Powered by your attendance engine v1.0
                </p>
            </div>
        </div>
    </div>
</x-app-layout>