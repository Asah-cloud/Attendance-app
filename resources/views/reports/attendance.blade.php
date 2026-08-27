<x-app-layout>
    <x-slot name="header">
        Reports
        <div class="hidden">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="font-extrabold text-2xl text-white tracking-tight">
                        {{ __('Event Analytics') }}
                    </h2>
                    @if($selectedDay === 'all')
                        <span class="bg-purple-600 text-white text-[9px] uppercase px-2 py-1 rounded-md font-black tracking-widest shadow-sm">
                            Consolidated
                        </span>
                    @endif
                </div>
                <p class="text-sm text-slate-300 font-medium mt-1">Detailed attendance breakdown and registry logs</p>
            </div>
            
            <div class="flex items-center gap-3 print:hidden">
                <a href="{{ route('events.attendance', ['event' => $event->id, 'day' => ($selectedDay === 'all' ? 1 : $selectedDay)]) }}" 
                   class="inline-flex items-center px-4 py-2 bg-white border-2 border-gray-100 rounded-xl font-black text-xs text-gray-600 uppercase tracking-widest hover:bg-gray-50 transition-all active:scale-95 shadow-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Back
                </a>
                
                <a href="{{ route('reports.excel', ['event' => $event->id, 'day' => $selectedDay]) }}" 
                   class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-xl font-black text-xs uppercase tracking-widest hover:bg-green-700 transition-all shadow-lg shadow-green-100 active:scale-95">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Excel
                </a>
                
                <button onclick="window.print()" 
                        class="inline-flex items-center px-4 py-2 bg-gray-900 text-white rounded-xl font-black text-xs uppercase tracking-widest hover:bg-black transition-all shadow-lg shadow-gray-200 active:scale-95">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 00-2 2h2m2 4h10a2 2 0 002-2v-4H5v4a2 2 0 002 2z"></path></svg>
                    PDF
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-12 bg-gray-50/50 min-h-screen">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-col justify-between gap-4 print:hidden sm:flex-row sm:items-center">
                <div><p class="text-xs font-extrabold uppercase tracking-[0.18em] text-blue-600">Attendance analytics</p><h2 class="mt-1 text-2xl font-black text-slate-950">{{ $event->title }}</h2><p class="mt-1 text-sm text-slate-500">Detailed attendance breakdown and registry logs.</p></div>
                <div class="flex flex-wrap gap-2"><a href="{{ route('events.attendance', ['event' => $event->id, 'day' => ($selectedDay === 'all' ? 1 : $selectedDay)]) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-extrabold text-slate-600 hover:border-blue-300 hover:text-blue-700">Back to attendance</a><a href="{{ route('reports.excel', ['event' => $event->id, 'day' => $selectedDay]) }}" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-extrabold text-white hover:bg-emerald-700">Export Excel</a><a href="{{ route('reports.pdf', ['event' => $event->id, 'day' => $selectedDay]) }}" class="rounded-xl bg-red-600 px-4 py-2.5 text-xs font-extrabold text-white hover:bg-red-700">Export PDF</a><button onclick="window.print()" class="rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-extrabold text-white hover:bg-slate-800">Print / PDF</button></div>
            </div>
            
            {{-- Print-Only Header (Hidden on Web) --}}
            <div class="hidden print:block mb-8 border-b-4 border-gray-900 pb-4">
                <h1 class="text-3xl font-black uppercase text-gray-900">{{ $event->title }}</h1>
                <p class="text-lg font-bold text-blue-600 uppercase tracking-widest">Attendance Registry Report</p>
                <p class="text-sm text-gray-500 mt-1">Generated on: {{ now()->format('F d, Y - h:i A') }}</p>
            </div>

            {{-- 1. Event Info Card --}}
            <div class="bg-white p-8 rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.02)] mb-8 border border-gray-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="flex items-center gap-4">
                    <div class="p-4 bg-blue-50 text-blue-600 rounded-2xl">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-blue-600 uppercase tracking-[0.2em] mb-1">Event Reference</p>
                        <h3 class="text-xl font-black text-gray-900 leading-none">{{ $event->title }}</h3>
                        <p class="text-sm text-gray-500 mt-2 font-medium">
                            {{ \Carbon\Carbon::parse($event->event_date)->format('M d, Y') }} 
                            @if($event->end_date) — {{ \Carbon\Carbon::parse($event->end_date)->format('M d, Y') }} @endif
                        </p>
                    </div>
                </div>
                
                {{-- Day Selection Navigation --}}
                <div class="print:hidden w-full md:w-auto">
                    <x-day-selector :event="$event" :selectedDay="$selectedDay" />
                </div>
            </div>

            {{-- Category / Gender Filters --}}
            <form method="GET" action="{{ route('reports.event', ['event' => $event->id, 'day' => $selectedDay]) }}" class="mb-8 flex flex-wrap items-center gap-3 print:hidden">
                <span class="text-xs font-black uppercase tracking-widest text-gray-400">Filter registry</span>
                <select name="category" onchange="this.form.submit()" class="rounded-xl border-gray-200 text-xs font-bold">
                    <option value="">All categories</option>
                    @foreach($availableCategories as $option)<option value="{{ $option }}" @selected($filterCategory === $option)>{{ $option }}</option>@endforeach
                </select>
                <select name="gender" onchange="this.form.submit()" class="rounded-xl border-gray-200 text-xs font-bold">
                    <option value="">All genders</option>
                    @foreach($availableGenders as $option)<option value="{{ $option }}" @selected($filterGender === $option)>{{ $option }}</option>@endforeach
                </select>
                @if($filterCategory !== '' || $filterGender !== '')
                    <a href="{{ route('reports.event', ['event' => $event->id, 'day' => $selectedDay]) }}" class="text-xs font-bold text-slate-500 underline">Clear filters</a>
                @endif
            </form>

            {{-- 2. Summary Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
                <x-summary-card title="Total Registered" :value="$totalExpected" color="blue" />
                <x-summary-card title="Present Members" :value="$presentUsers->count()" color="green" />
                <x-summary-card title="Absent Members" :value="$absentUsers->count()" color="red" />
            </div>

            {{-- Category / Gender Breakdown --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-12 print:hidden">
                <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-xs font-black uppercase tracking-widest text-gray-400">Present by category</h3>
                    <div class="flex flex-wrap gap-2">
                        @forelse($categoryBreakdown as $label => $count)
                            <span class="rounded-full bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700">{{ $label }} · {{ $count }}</span>
                        @empty
                            <p class="text-sm text-gray-400">No attendance yet.</p>
                        @endforelse
                    </div>
                </div>
                <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-xs font-black uppercase tracking-widest text-gray-400">Present by gender</h3>
                    <div class="flex flex-wrap gap-2">
                        @forelse($genderBreakdown as $label => $count)
                            <span class="rounded-full bg-purple-50 px-3 py-1.5 text-xs font-bold text-purple-700">{{ $label }} · {{ $count }}</span>
                        @empty
                            <p class="text-sm text-gray-400">No attendance yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- 3. Lists Staggered Layout --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
                
                {{-- Present Members List --}}
                <div x-data="{ open: true }" class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden animate-in slide-in-from-left-4 duration-500">
                    <button type="button" @click="open = !open" :aria-expanded="open.toString()" class="w-full px-6 py-5 bg-green-600 flex justify-between items-center text-left hover:bg-green-700">
                        <h3 class="font-black text-white uppercase text-xs tracking-widest">Present Registry</h3>
                        <span class="flex items-center gap-2 bg-white/20 text-white px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-tighter">{{ $event->attendanceSessionLabel($selectedDay) }}<svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="m6 9 6 6 6-6" /></svg></span>
                    </button>
                    <div x-cloak x-show="open" x-transition class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-gray-50/50 text-[10px] uppercase text-gray-400 font-black border-b border-gray-50">
                                    <th class="px-6 py-4 w-12 text-center">#</th>
                                    <th class="px-6 py-4">Name</th>
                                    <th class="px-6 py-4 text-center">{{ $selectedDay === 'all' ? 'Freq.' : 'Time' }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @forelse($presentUsers as $index => $user)
                                <tr class="text-sm group hover:bg-green-50/30 transition-colors">
                                    <td class="px-6 py-4 text-gray-400 text-center font-mono text-xs">{{ $index + 1 }}</td>
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-gray-800">{{ $user->name }}</div>
                                        <div class="text-[10px] text-gray-400 font-mono tracking-tighter">{{ $user->phone }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        @if($selectedDay === 'all')
                                            <span class="bg-green-100 text-green-700 px-2 py-1 rounded text-[9px] font-black uppercase">
                                                {{ $user->attendances->count() }}x
                                            </span>
                                        @else
                                            <span class="text-green-600 font-black text-[11px] uppercase">
                                                {{ $user->attendances->first() ? $user->attendances->first()->created_at->format('h:i A') : '--:--' }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="3" class="p-12 text-center text-gray-400 font-medium italic">No attendance records found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Absent Members List --}}
                <div x-data="{ open: false }" class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden animate-in slide-in-from-right-4 duration-500">
                    <button type="button" @click="open = !open" :aria-expanded="open.toString()" class="w-full px-6 py-5 bg-red-600 flex justify-between items-center text-left hover:bg-red-700">
                        <h3 class="font-black text-white uppercase text-xs tracking-widest">Absent Registry</h3>
                        <span class="flex items-center gap-2 bg-white/20 text-white px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-tighter">{{ $absentUsers->count() }} people<svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="m6 9 6 6 6-6" /></svg></span>
                    </button>
                    <div x-cloak x-show="open" x-transition class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-gray-50/50 text-[10px] uppercase text-gray-400 font-black border-b border-gray-50">
                                    <th class="px-6 py-4 w-12 text-center">#</th>
                                    <th class="px-6 py-4">Name</th>
                                    <th class="px-6 py-4 text-center">Category</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @forelse($absentUsers->values() as $index => $user)
                                <tr class="text-sm group hover:bg-red-50/30 transition-colors">
                                    <td class="px-6 py-4 text-gray-400 text-center font-mono text-xs">{{ $index + 1 }}</td>
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-gray-800">{{ $user->name }}</div>
                                        <div class="text-[10px] text-gray-400 font-mono tracking-tighter">{{ $user->phone }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-2 py-1 bg-gray-100 rounded text-[9px] font-black uppercase text-gray-500 border border-gray-200">
                                            {{ $user->category ?? 'General' }}
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="3" class="p-12 text-center text-green-600 font-black uppercase tracking-widest italic">Complete Attendance!</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
