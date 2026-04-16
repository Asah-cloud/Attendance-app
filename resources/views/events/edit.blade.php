<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-white/20 rounded-lg">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
            </div>
            <h2 class="font-bold text-2xl text-white leading-tight">
                {{ __('Edit Event Settings') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12 bg-[#fcfcfd]">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 pb-12">
            
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
                {{-- Form Header with subtle gradient accent --}}
                <div class="relative bg-gray-50/50 p-8 border-b border-gray-100">
                    <div class="absolute top-0 left-0 w-1.5 h-full bg-gradient-to-b from-blue-600 to-red-600"></div>
                    <h2 class="text-xl font-black text-gray-900 tracking-tight">Modify Event Information</h2>
                    <p class="text-sm text-gray-500 mt-1 font-medium">Keep your congregation updated by ensuring these details are accurate.</p>
                </div>

                <form action="{{ route('events.update', $event->id) }}" method="POST" class="p-8 space-y-8">
                    @csrf
                    @method('PUT')

                    {{-- Event Title --}}
                    <div class="group">
                        <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2 group-focus-within:text-blue-600 transition-colors">Event Title</label>
                        <input type="text" name="title" value="{{ old('title', $event->title) }}" 
                               class="w-full border-gray-200 bg-gray-50/30 rounded-xl px-4 py-3 shadow-sm focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 focus:bg-white transition-all outline-none" 
                               placeholder="e.g. Sunday Morning Service" required>
                        @error('title') <p class="text-red-500 text-xs mt-2 font-bold">{{ $message }}</p> @enderror
                    </div>

                    {{-- Date Grid --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        {{-- Start Date --}}
                        <div>
                            <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Start Date</label>
                            <input type="date" name="event_date" 
                                   value="{{ old('event_date', $event->event_date ? $event->event_date->format('Y-m-d') : '') }}" 
                                   class="w-full border-gray-200 bg-gray-50/30 rounded-xl px-4 py-3 shadow-sm focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 focus:bg-white transition-all outline-none" required>
                            @error('event_date') <p class="text-red-500 text-xs mt-2 font-bold">{{ $message }}</p> @enderror
                        </div>

                        {{-- End Date --}}
                        <div>
                            <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">End Date (Optional)</label>
                            <input type="date" name="end_date" 
                                   value="{{ old('end_date', $event->end_date ? $event->end_date->format('Y-m-d') : '') }}" 
                                   class="w-full border-gray-200 bg-gray-50/30 rounded-xl px-4 py-3 shadow-sm focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 focus:bg-white transition-all outline-none">
                            <p class="text-[10px] text-gray-400 mt-2 font-medium italic">Leave blank for single-day services.</p>
                            @error('end_date') <p class="text-red-500 text-xs mt-2 font-bold">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Description --}}
                    <div>
                        <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Event Description</label>
                        <textarea name="description" rows="4" 
                                  class="w-full border-gray-200 bg-gray-50/30 rounded-xl px-4 py-3 shadow-sm focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 focus:bg-white transition-all outline-none" 
                                  placeholder="Provide brief details about the service...">{{ old('description', $event->description) }}</textarea>
                    </div>

                    {{-- Location --}}
                    <div>
                        <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Venue / Hall</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center ps-4 text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </span>
                            <input type="text" name="location" value="{{ old('location', $event->location) }}" 
                                   class="w-full border-gray-200 bg-gray-50/30 rounded-xl ps-10 pe-4 py-3 shadow-sm focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 focus:bg-white transition-all outline-none" 
                                   placeholder="Main Sanctuary, Youth Hall, etc.">
                        </div>
                    </div>

                    <div class="pt-6 border-t border-gray-50 flex flex-col sm:flex-row justify-end items-center gap-6">
                        <a href="{{ route('events.index') }}" class="text-sm font-bold text-gray-400 hover:text-red-600 transition-colors">Discard Changes</a>
                        
                        <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center px-10 py-4 bg-gradient-to-r from-blue-700 to-blue-800 text-white rounded-xl font-black text-sm uppercase tracking-widest shadow-xl shadow-blue-200 hover:shadow-blue-300 hover:-translate-y-0.5 active:translate-y-0 transition-all">
                            Update Event
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>