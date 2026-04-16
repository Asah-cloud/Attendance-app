@props(['event'])

@php
    $start = \Carbon\Carbon::parse($event->event_date)->startOfDay();
    $end = $event->end_date ? \Carbon\Carbon::parse($event->end_date)->startOfDay() : $start;
    $totalDays = $start->diffInDays($end) + 1;
    $isMultiDay = $totalDays > 1;
    $currentDay = request('day', 1);
@endphp

@if($isMultiDay)
    <div class="mt-6 p-5 bg-white border border-blue-100 rounded-3xl flex flex-col sm:flex-row items-start sm:items-center justify-between shadow-[0_10px_25px_rgba(59,130,246,0.05)] gap-4">
        <div class="flex items-center gap-4">
            <div class="relative">
                <div class="absolute inset-0 bg-blue-400 rounded-xl animate-ping opacity-20"></div>
                <div class="relative p-3 bg-blue-600 rounded-xl text-white shadow-lg shadow-blue-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
            </div>
            
            <div>
                <span class="text-[10px] font-black text-blue-500 uppercase tracking-[0.2em] block mb-0.5">Timeline Status</span>
                <h3 class="text-xl font-black text-gray-900 leading-none tracking-tighter uppercase">
                    Day <span class="text-blue-600">{{ $currentDay }}</span> <span class="text-gray-300 mx-1">/</span> {{ $totalDays }}
                </h3>
            </div>
        </div>

        {{-- Enhanced Select Switcher --}}
        <div class="relative w-full sm:w-auto">
            <select onchange="window.location.href = '?day=' + this.value"
                    class="w-full sm:w-64 pl-4 pr-12 py-3 text-[11px] font-black uppercase tracking-widest border-none rounded-2xl bg-blue-50 text-blue-700 cursor-pointer focus:ring-2 focus:ring-blue-500/20 outline-none transition-all appearance-none shadow-inner">
                @for($i = 1; $i <= $totalDays; $i++)
                    <option value="{{ $i }}" {{ $currentDay == $i ? 'selected' : '' }}>
                        Switch to Day {{ $i }}
                    </option>
                @endfor
            </select>
            
            {{-- Custom Chevron --}}
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-blue-600">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7"></path>
                </svg>
            </div>
        </div>
    </div>
@endif