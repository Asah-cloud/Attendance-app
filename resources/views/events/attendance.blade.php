<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <div>
                <h2 class="font-black text-2xl text-white leading-tight tracking-tight">
                    {{ $event->title }}
                </h2>
                <div class="flex items-center gap-2 mt-1">
                    <span class="px-2 py-0.5 bg-white/20 rounded text-[10px] font-bold text-white uppercase tracking-widest">
                        {{ \Carbon\Carbon::parse($event->event_date)->format('M d, Y') }}
                    </span>
                    @if($event->end_date)
                        <span class="text-white/60 text-xs">—</span>
                        <span class="px-2 py-0.5 bg-white/20 rounded text-[10px] font-bold text-white uppercase tracking-widest">
                            {{ \Carbon\Carbon::parse($event->end_date)->format('M d, Y') }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('reports.event', ['event' => $event->id, 'day' => $currentDay]) }}" 
                   class="inline-flex items-center px-5 py-2.5 bg-white text-purple-700 rounded-xl font-black text-xs uppercase tracking-widest shadow-xl hover:bg-purple-50 transition-all active:scale-95">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Day {{ $currentDay }} Report
                </a>

                <a href="{{ route('events.index') }}" class="p-2.5 bg-white/10 text-white hover:bg-white/20 rounded-xl transition-all" title="Back to Events">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-10 bg-[#f8fafc]">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            @php $status = $event->status; @endphp

            {{-- 1. Dynamic Status Banners --}}
            @if($status === 'active')
                <div class="bg-gradient-to-r from-blue-700 to-blue-600 text-white p-5 mb-8 shadow-xl shadow-blue-100 flex items-center justify-between rounded-2xl border border-blue-400/30">
                    <div class="flex items-center gap-4">
                        <div class="relative">
                            <div class="absolute inset-0 bg-white rounded-full animate-ping opacity-20"></div>
                            <div class="relative p-3 bg-white/20 rounded-full">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                        </div>
                        <div>
                            <span class="block text-[10px] uppercase font-black text-blue-200 tracking-[0.2em] leading-none mb-1">Live Tracking Enabled</span>
                            <span class="text-xl font-black italic">Session Day {{ $currentDay }}</span>
                        </div>
                    </div>
                   
                    @if(auth()->user()->role === 'admin')
                        <div class="bg-white/10 p-1.5 rounded-xl border border-white/10">
                            <x-day-switcher :event="$event" />
                        </div>
                    @endif
                </div>
            @elseif($status === 'closed')
                <div class="bg-red-50 border border-red-100 text-red-700 p-5 mb-8 shadow-sm flex items-center gap-3 rounded-2xl">
                    <div class="p-2 bg-red-100 rounded-lg text-red-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <span class="font-bold">Event Closed:</span>
                    <span class="text-red-500 font-medium italic underline decoration-red-200">View-only mode active.</span>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                {{-- LEFT COLUMN: QR & Stats --}}
                <div class="lg:col-span-1 space-y-8">
                    {{-- QR Code Section --}}
                    <div class="bg-white p-8 rounded-3xl shadow-[0_15px_50px_rgba(0,0,0,0.03)] border border-gray-100 text-center relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-blue-50 rounded-full -mr-16 -mt-16"></div>
                        
                        <h3 class="font-black text-gray-400 mb-6 uppercase text-[10px] tracking-[0.3em] relative z-10">Scan to Check-in</h3>
                        
                        <div class="relative z-10 inline-block p-6 bg-white border border-gray-100 rounded-[2.5rem] mb-6 shadow-2xl transition-transform hover:scale-105 duration-500">
                            {!! QrCode::size(200)->color(30, 58, 138)->generate("http://192.168.8.107:8000/scan/" . $event->id . "?day=" . $currentDay) !!}
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100">
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Current Portal Access</p>
                            <p class="text-2xl font-black text-blue-900 leading-none">Day {{ $currentDay }}</p>
                        </div>
                    </div>

                    {{-- Stats Summary --}}
                    <div class="bg-blue-900 rounded-3xl p-8 text-white shadow-xl shadow-blue-200 relative overflow-hidden">
                        <div class="absolute bottom-0 left-0 w-full h-1 bg-gradient-to-r from-blue-400 to-red-500"></div>
                        <h3 class="font-bold text-blue-300 mb-6 text-xs uppercase tracking-widest">Attendance Pulse</h3>
                        <div class="space-y-6">
                            <div class="flex justify-between items-end">
                                <span class="text-blue-100/70 text-sm font-medium">Invited Guests</span>
                                <span class="text-3xl font-black leading-none">{{ number_format($totalMembers) }}</span>
                            </div>
                            <div class="flex justify-between items-end pb-2">
                                <span class="text-blue-100/70 text-sm font-medium">Verified Present</span>
                                <span class="text-3xl font-black text-green-400 leading-none">{{ number_format($presentCount) }}</span>
                            </div>
                            {{-- Progress Bar --}}
                            <div class="w-full bg-white/10 rounded-full h-2">
                                <div class="bg-green-400 h-2 rounded-full shadow-[0_0_10px_rgba(74,222,128,0.5)]" style="width: {{ $totalMembers > 0 ? ($presentCount/$totalMembers)*100 : 0 }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- RIGHT COLUMN: Search & List --}}
                <div class="lg:col-span-2 space-y-8">
                    @role('admin')
                    <div class="bg-white p-6 border border-gray-100 rounded-2xl shadow-sm group">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-2 bg-gray-50 rounded-lg group-hover:bg-blue-50 transition-colors">
                                <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                            </div>
                            <h3 class="font-black text-gray-700 uppercase text-xs tracking-widest">Sync Guest Database (Excel/CSV)</h3>
                        </div>
                        <form action="{{ route('users.import', $event->id) }}" method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-3">
                            @csrf
                            <input type="file" name="file" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-black file:uppercase file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-all border border-gray-100 rounded-xl" required>
                            <button type="submit" class="bg-gray-900 text-white px-8 py-2.5 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-black hover:shadow-lg transition-all active:scale-95">
                                Import
                            </button>
                        </form>
                    </div>
                    @endrole

                    {{-- Manual Search Section --}}
                    <div class="bg-white shadow-[0_15px_50px_rgba(0,0,0,0.03)] border border-gray-100 rounded-3xl overflow-hidden">
                        <div class="bg-gray-50/50 p-6 border-b border-gray-100 flex justify-between items-center">
                            <div>
                                <h3 class="font-black text-gray-900 tracking-tight">Manual Check-in</h3>
                                <p class="text-[10px] text-blue-600 uppercase font-bold tracking-widest mt-1">Registry for Day {{ $currentDay }}</p>
                            </div>
                            <div class="px-3 py-1 bg-green-100 rounded-full">
                                <span class="text-[10px] font-black text-green-700 uppercase">Live Sync</span>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <livewire:attendance-search :event="$event" :day="$currentDay" :key="'attendance-search-'.$currentDay" />
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>