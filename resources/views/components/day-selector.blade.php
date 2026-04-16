@props(['event', 'selectedDay'])

<div class="mb-10 flex flex-col md:flex-row items-start md:items-center gap-6 bg-white p-6 rounded-3xl shadow-[0_10px_30px_rgba(0,0,0,0.02)] border border-gray-100 print:hidden">
    <div class="flex items-center gap-2 min-w-max">
        <div class="p-1.5 bg-gray-100 rounded-lg text-gray-500">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
        </div>
        <span class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Select Period</span>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <div class="flex bg-gray-50 p-1.5 rounded-2xl border border-gray-100">
            @for($i = 1; $i <= ($event->day ?? 1); $i++)
                <button wire:click="setDay({{ $i }})"
                    class="px-5 py-2 rounded-xl text-xs font-black uppercase tracking-widest transition-all duration-200 
                           {{ $selectedDay == $i 
                              ? 'bg-white text-blue-600 shadow-sm ring-1 ring-black/5 scale-105' 
                              : 'text-gray-400 hover:text-gray-600 hover:bg-white/50' }}">
                    Day {{ $i }}
                </button>
            @endfor
        </div>

        <div class="h-6 w-[1px] bg-gray-200 hidden md:block"></div>

        {{-- Final Analytics Link --}}
        <a href="{{ route('reports.summary', $event->id) }}" 
           class="px-5 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-[0.15em] bg-purple-600 text-white hover:bg-purple-700 hover:shadow-lg hover:shadow-purple-100 transition-all active:scale-95 shadow-sm">
           <span class="flex items-center gap-2">
               <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
               Master Summary
           </span>
        </a>
    </div>
</div>