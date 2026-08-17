@props(['event', 'selectedDay'])

@php
    $totalDays = $event->days_count ?? $event->day;

    if ((!$totalDays || $totalDays <= 1) && $event->end_date) {
        $start = \Carbon\Carbon::parse($event->event_date);
        $end = \Carbon\Carbon::parse($event->end_date);
        $totalDays = $start->diffInDays($end) + 1;
    }

    $totalDays = max(1, $totalDays);
@endphp

<div class="mb-10 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white p-5 rounded-3xl shadow-[0_10px_30px_rgba(0,0,0,0.02)] border border-gray-100 print:hidden">
    <div class="flex items-center gap-4 w-full sm:w-auto">
        {{-- Icon Decoration --}}
        <div class="p-2 bg-gray-50 rounded-xl text-gray-400 hidden xs:block">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
        </div>

        {{-- Interactive Dropdown Engine --}}
        <div x-data="{ open: false }" @click.away="open = false" class="relative inline-block text-left w-full sm:w-64">
            <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1">Filter Report Period</span>
            
            <button @click="open = !open" type="button" 
                    class="inline-flex items-center justify-between w-full rounded-2xl border border-gray-200 bg-white px-4 py-2.5 text-xs font-bold text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none transition-all">
                <span>
                    @if($selectedDay === 'all')
                        ✨ All Event Days Consolidated
                    @else
                        📅 Day {{ $selectedDay }} Report
                    @endif
                </span>
                <svg class="ml-2 h-4 w-4 text-gray-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>

            {{-- Dropdown Menu Items --}}
            <div x-show="open" 
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="transform opacity-0 scale-95"
                 x-transition:enter-end="transform opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="transform opacity-100 scale-100"
                 x-transition:leave-end="transform opacity-0 scale-95"
                 class="absolute left-0 z-50 mt-2 w-full origin-top-left rounded-2xl bg-white shadow-xl ring-1 ring-black/5 border border-gray-50 focus:outline-none max-h-60 overflow-y-auto divide-y divide-gray-50" style="display: none;">
                
                <div class="py-1">
                    <a href="{{ route('reports.event', ['event' => $event->id, 'day' => 'all']) }}" 
                       class="flex items-center justify-between px-4 py-2.5 text-xs font-semibold transition-colors
                              {{ $selectedDay === 'all' ? 'bg-purple-50 text-purple-700 font-bold' : 'text-gray-700 hover:bg-gray-50' }}">
                        <span>All Days Overview</span>
                        @if($selectedDay === 'all')
                            <span class="w-1.5 h-1.5 bg-purple-600 rounded-full"></span>
                        @endif
                    </a>
                </div>

                <div class="py-1">
                    @for($i = 1; $i <= $totalDays; $i++)
                        <a href="{{ route('reports.event', ['event' => $event->id, 'day' => $i]) }}" 
                           class="flex items-center justify-between px-4 py-2.5 text-xs font-semibold transition-colors
                                  {{ (string)$selectedDay === (string)$i ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-600 hover:bg-gray-50' }}">
                            <span>Day {{ $i }}</span>
                            @if((string)$selectedDay === (string)$i)
                                <span class="w-1.5 h-1.5 bg-blue-600 rounded-full"></span>
                            @endif
                        </a>
                    @endfor
                </div>
            </div>
        </div>
    </div>

    {{-- Master Summary Link (Stays on the right side on desktop) --}}
    <a href="{{ route('reports.summary', ['event' => $event->id]) }}" 
       class="w-full sm:w-auto text-center px-5 py-3 rounded-2xl text-[10px] font-black uppercase tracking-[0.15em] bg-purple-600 text-white hover:bg-purple-700 hover:shadow-lg hover:shadow-purple-100 transition-all active:scale-95 shadow-sm flex items-center justify-center gap-2">
       <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a２ ２ ０ ０１－２ Ｘ－２ｈ－２ａ２ Ｘ－２ Ｘ－２ｚ"></path>
       </svg>
       Master Summary
    </a>
</div>