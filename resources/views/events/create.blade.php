<x-app-layout>
    <x-slot name="header">Create Event</x-slot>

    <div class="py-12 bg-[#fcfcfd]">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            
            <div class="bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-100 overflow-hidden">
                {{-- Branded Form Header --}}
                <div class="relative bg-gray-50/50 p-8 border-b border-gray-100">
                    <div class="absolute top-0 left-0 w-1.5 h-full bg-gradient-to-b from-blue-600 to-red-600"></div>
                    <h2 class="text-xl font-black text-gray-900 tracking-tight">Event Configuration</h2>
                    <p class="text-sm text-gray-500 mt-1 font-medium">Fill in the details below to open attendance for a new service or conference.</p>
                </div>

                <form action="{{ route('events.store') }}" method="POST" enctype="multipart/form-data" class="p-8 space-y-8">
                    @csrf
                    
                    {{-- Pass down the company id automatically if it exists --}}
    @if(isset($company_id))
        <input type="hidden" name="company_id" value="{{ $company_id }}">
    @endif
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

                    <div x-data="{ enabled: {{ old('has_arrival_session') ? 'true' : 'false' }} }" class="rounded-2xl border border-cyan-100 bg-cyan-50/60 p-6">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" name="has_arrival_session" value="1" x-model="enabled" class="mt-1 rounded border-cyan-300 text-cyan-600 focus:ring-cyan-500">
                            <span><span class="block text-sm font-black text-cyan-950">Add a separate Arrival check-in</span><span class="mt-1 block text-xs leading-5 text-cyan-800/70">Track tag and event-essential collection separately from Day 1 attendance.</span></span>
                        </label>
                        <div x-show="enabled" x-cloak class="mt-5">
                            <label class="block text-xs font-black uppercase tracking-widest text-cyan-800">Arrival date</label>
                            <input type="date" name="arrival_date" value="{{ old('arrival_date') }}" :required="enabled" class="mt-2 w-full rounded-xl border-cyan-200 bg-white px-4 py-3">
                            <p class="mt-2 text-xs text-cyan-700">Choose the Day 1 date if Arrival and Day 1 happen on the same day.</p>
                            @error('arrival_date') <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p> @enderror
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

                    {{-- Event Logo --}}
                    <div>
                        <label for="logo" class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Event Logo (Optional)</label>
                        <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp"
                               class="block w-full rounded-xl border border-gray-200 bg-gray-50/30 p-3 text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-xs file:font-extrabold file:text-blue-700">
                        <p class="mt-2 text-[10px] text-gray-400 font-medium italic">PNG, JPG or WebP. Maximum size: 2 MB. Shown on the registration form and confirmation emails.</p>
                        @error('logo') <p class="text-red-500 text-xs mt-2 font-bold">{{ $message }}</p> @enderror
                    </div>

                    {{-- Event Flyer --}}
                    <div>
                        <label for="flyer" class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Event Flyer (Optional)</label>
                        <input id="flyer" name="flyer" type="file" accept="image/png,image/jpeg,image/webp"
                               class="block w-full rounded-xl border border-gray-200 bg-gray-50/30 p-3 text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-xs file:font-extrabold file:text-blue-700">
                        <p class="mt-2 text-[10px] text-gray-400 font-medium italic">PNG, JPG or WebP. Maximum size: 2 MB. Used as the background of the hard-copy attendance confirmation page.</p>
                        @error('flyer') <p class="text-red-500 text-xs mt-2 font-bold">{{ $message }}</p> @enderror
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
