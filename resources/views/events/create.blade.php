<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-white/20 rounded-lg">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
            </div>
            <h2 class="font-bold text-2xl text-white leading-tight">
                {{ __('Setup New Event') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12 bg-[#fcfcfd]">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            
            {{-- Navigation Back --}}
            <div class="mb-8">
                <a href="{{ route('events.index') }}" class="group inline-flex items-center text-blue-700 hover:text-red-600 font-semibold text-sm transition-colors">
                    <div class="p-1 bg-blue-50 group-hover:bg-red-50 rounded-md me-2 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </div>
                    Back to Event List
                </a>
            </div>

            <div class="bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-100 overflow-hidden">
                {{-- Branded Form Header --}}
                <div class="relative bg-gray-50/50 p-8 border-b border-gray-100">
                    <div class="absolute top-0 left-0 w-1.5 h-full bg-gradient-to-b from-blue-600 to-red-600"></div>
                    <h2 class="text-xl font-black text-gray-900 tracking-tight">Event Configuration</h2>
                    <p class="text-sm text-gray-500 mt-1 font-medium">Fill in the details below to open attendance for a new service or conference.</p>
                </div>

                <form action="{{ route('events.store') }}" method="POST" class="p-8 space-y-8">
                    @csrf
                    
                    {{-- Event Title --}}
                    <div class="group">
                        <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2 group-focus-within:text-blue-600 transition-colors">Event Title</label>
                        <input type="text" name="title" value="{{ old('title') }}" 
                               placeholder="e.g. Sunday Morning Service" 
                               class="w-full border-gray-200 bg-gray-50/30 rounded-xl px-4 py-3 shadow-sm focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 focus:bg-white transition-all outline-none" required>
                        @error('title') <p class="text-red-500 text-xs mt-2 font-bold">{{ $message }}</p> @enderror
                    </div>

                    {{-- Dates and Times Grid --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Start Date & Time</label>
                            <input type="datetime-local" name="event_date" value="{{ old('event_date') }}"
                                   class="w-full border-gray-200 bg-gray-50/30 rounded-xl px-4 py-3 shadow-sm focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 focus:bg-white transition-all outline-none" required>
                            @error('event_date') <p class="text-red-500 text-xs mt-2 font-bold">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">End Date & Time</label>
                            <input type="datetime-local" name="end_date" value="{{ old('end_date') }}"
                                   class="w-full border-gray-200 bg-gray-50/30 rounded-xl px-4 py-3 shadow-sm focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 focus:bg-white transition-all outline-none">
                            @error('end_date') <p class="text-red-500 text-xs mt-2 font-bold">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Conference Day --}}
                    <div class="inline-block p-6 bg-blue-50/50 rounded-2xl border border-blue-100">
                        <label class="block text-xs font-black uppercase tracking-widest text-blue-800 mb-2">Conference Day</label>
                        <div class="flex items-center gap-4">
                            <input type="number" name="day" value="{{ old('day', 1) }}" 
                                   class="w-24 border-blue-200 bg-white rounded-xl px-4 py-3 shadow-sm focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none text-center font-bold text-blue-900" 
                                   placeholder="1">
                            <p class="text-[11px] text-blue-600/70 font-bold uppercase leading-tight">The specific day number<br>within the program.</p>
                        </div>
                        @error('day') <p class="text-red-500 text-xs mt-2 font-bold">{{ $message }}</p> @enderror
                    </div>

                    {{-- Description --}}
                    <div>
                        <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Description (Optional)</label>
                        <textarea name="description" rows="4" placeholder="Briefly describe the purpose of this event..." 
                                  class="w-full border-gray-200 bg-gray-50/30 rounded-xl px-4 py-3 shadow-sm focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 focus:bg-white transition-all outline-none">{{ old('description') }}</textarea>
                        @error('description') <p class="text-red-500 text-xs mt-2 font-bold">{{ $message }}</p> @enderror
                    </div>

                    {{-- Action Buttons --}}
                    <div class="pt-6 border-t border-gray-50 flex flex-col sm:flex-row justify-end items-center gap-6">
                        <a href="{{ route('events.index') }}" class="text-sm font-bold text-gray-400 hover:text-red-600 transition-colors uppercase tracking-widest">Cancel</a>
                        
                        <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center px-10 py-4 bg-gradient-to-r from-blue-700 to-blue-800 text-white rounded-xl font-black text-sm uppercase tracking-widest shadow-xl shadow-blue-200 hover:shadow-blue-300 hover:-translate-y-0.5 active:translate-y-0 transition-all">
                            <svg class="w-5 h-5 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Create Event
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>