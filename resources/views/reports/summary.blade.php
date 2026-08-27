<x-app-layout>
    <x-slot name="header">
        Summary Report
        <div class="hidden">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-purple-600 rounded-lg shadow-lg shadow-purple-200 text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <h2 class="font-extrabold text-xl text-white tracking-tight">
                    {{ __('Final Performance Summary') }}
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="py-12 bg-gray-50/50 min-h-screen">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            
            {{-- 1. Summary Header Card --}}
            <div class="bg-white p-8 rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] mb-8 border border-gray-100 flex flex-col md:flex-row justify-between items-center gap-6 relative overflow-hidden">
                <div class="absolute top-0 left-0 w-2 h-full bg-purple-600"></div>
                <div>
                    <span class="text-[10px] font-black text-purple-600 uppercase tracking-[0.3em] block mb-2">Final Report</span>
                    <h1 class="text-3xl font-black text-gray-900 leading-tight uppercase tracking-tighter">{{ $event->title }}</h1>
                    <p class="text-sm text-gray-500 font-medium mt-1">Completion Summary for all scheduled days</p>
                </div>
                <div class="flex gap-3 print:hidden">
                    <a href="{{ route('reports.summary.export', $event->id) }}" class="inline-flex items-center px-5 py-2.5 bg-green-600 text-white rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-green-700 transition-all shadow-lg shadow-green-100">
                        Export Master File
                    </a>
                    <a href="{{ route('reports.summary.pdf', $event->id) }}" class="inline-flex items-center px-5 py-2.5 bg-red-600 text-white rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-red-700 transition-all shadow-lg shadow-red-100">
                        Export PDF
                    </a>
                    <button onclick="window.print()" class="inline-flex items-center px-5 py-2.5 bg-gray-900 text-white rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-black transition-all shadow-lg shadow-gray-200">
                        Print Registry
                    </button>
                </div>
            </div>

            {{-- 2. Performance Stats --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-12">
                <div class="group bg-white p-8 rounded-3xl shadow-sm border border-gray-100 flex items-center justify-between hover:-translate-y-1 hover:shadow-xl">
                    <div>
                        <p class="text-gray-400 text-[10px] uppercase font-black tracking-widest">Active Engagement</p>
                        <p class="text-5xl font-black text-gray-900 mt-2 tracking-tighter">{{ $presentUsers->count() }}</p>
                        <p class="text-xs font-bold text-green-600 uppercase mt-1">Unique Attendees</p>
                    </div>
                    <div class="h-16 w-16 bg-green-50 rounded-2xl flex items-center justify-center text-green-600">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                </div>
                <div class="group bg-white p-8 rounded-3xl shadow-sm border border-gray-100 flex items-center justify-between hover:-translate-y-1 hover:shadow-xl">
                    <div>
                        <p class="text-gray-400 text-[10px] uppercase font-black tracking-widest">Non-Attendance</p>
                        <p class="text-5xl font-black text-gray-900 mt-2 tracking-tighter">{{ $absentUsers->count() }}</p>
                        <p class="text-xs font-bold text-red-600 uppercase mt-1">Never Attended</p>
                    </div>
                    <div class="h-16 w-16 bg-red-50 rounded-2xl flex items-center justify-center text-red-600">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                    </div>
                </div>
            </div>

            {{-- 2b. Category / Gender Breakdown --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-12 print:hidden">
                <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-xs font-black uppercase tracking-widest text-gray-400">Attendees by category</h3>
                    <div class="flex flex-wrap gap-2">
                        @forelse($categoryBreakdown as $label => $count)
                            <span class="rounded-full bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700">{{ $label }} · {{ $count }}</span>
                        @empty
                            <p class="text-sm text-gray-400">No attendance yet.</p>
                        @endforelse
                    </div>
                </div>
                <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-xs font-black uppercase tracking-widest text-gray-400">Attendees by gender</h3>
                    <div class="flex flex-wrap gap-2">
                        @forelse($genderBreakdown as $label => $count)
                            <span class="rounded-full bg-purple-50 px-3 py-1.5 text-xs font-bold text-purple-700">{{ $label }} · {{ $count }}</span>
                        @empty
                            <p class="text-sm text-gray-400">No attendance yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- 3. Detailed Attendance Table --}}
            <div x-data="{ open: false }" class="bg-white rounded-3xl shadow-sm overflow-hidden border border-gray-100">
                <button type="button" @click="open = !open" :aria-expanded="open.toString()" class="flex w-full items-center justify-between px-8 py-6 bg-gray-50/50 border-b border-gray-100 text-left hover:bg-blue-50">
                    <span><span class="block font-black text-gray-900 uppercase text-xs tracking-widest">Member Consistency Rankings</span><span class="mt-1 block text-xs text-slate-500">{{ $presentUsers->count() }} attendees · click to open or close</span></span>
                    <svg class="h-5 w-5 text-slate-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="m6 9 6 6 6-6" /></svg>
                </button>
                <div x-cloak x-show="open" x-transition class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-white text-gray-400 text-[10px] uppercase font-black border-b border-gray-100">
                                <th class="px-8 py-5">Attendee Profile</th>
                                <th class="px-8 py-5 text-center">Score</th>
                                <th class="px-8 py-5 text-center" style="width: 35%;">Retention Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($presentUsers->sortByDesc('days_attended') as $user)
                            <tr class="hover:bg-purple-50/30 transition-colors group">
                                <td class="px-8 py-5">
                                    <div class="font-black text-gray-800 uppercase text-sm tracking-tight group-hover:text-purple-700 transition-colors">{{ $user->name }}</div>
                                    <div class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-1">{{ $user->category ?? 'General Attendee' }}</div>
                                </td>
                                <td class="px-8 py-5 text-center">
                                    <span class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-700 rounded-lg text-[10px] font-black border border-gray-200 uppercase tracking-tighter">
                                        {{ $user->days_attended }} / {{ $totalEventDays }} Days
                                    </span>
                                </td>
                                <td class="px-8 py-5">
                                    <div class="flex items-center gap-4">
                                        <div class="flex-1 bg-gray-100 rounded-full h-2.5 overflow-hidden border border-gray-200">
                                            <div class="bg-purple-600 h-full rounded-full transition-all duration-1000 shadow-[0_0_10px_rgba(147,51,234,0.3)]" 
                                                 style="width: {{ $user->attendance_rate }}%"></div>
                                        </div>
                                        <span class="text-[11px] font-black text-gray-900 w-10 text-right italic">
                                            {{ round($user->attendance_rate) }}%
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Print Footer --}}
            <div class="hidden print:block mt-20 border-t pt-8">
                <div class="flex justify-between items-end">
                    <div class="text-sm text-gray-400 font-black uppercase tracking-[0.2em]">
                        Report Official Seal
                    </div>
                    <div class="text-right">
                        <div class="h-0.5 w-48 bg-gray-900 mb-2"></div>
                        <p class="text-xs font-black uppercase tracking-widest">Authorized Signature</p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
