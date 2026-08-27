<x-app-layout>
    <x-slot name="header">
        Attendance
        <div class="hidden">
            <div>
                <h2 class="font-black text-2xl text-white leading-tight tracking-tight">
                    {{ $event->title }}
                </h2>
                <div class="flex items-center gap-2 mt-1">
                    <span class="px-2 py-0.5 bg-white/20 rounded text-[10px] font-bold text-white uppercase tracking-widest hover:bg-white/30 transition-colors cursor-default">
                        {{ \Carbon\Carbon::parse($event->event_date)->format('M d, Y') }}
                    </span>
                    @if($event->end_date)
                        <span class="text-white/60 text-xs">—</span>
                        <span class="px-2 py-0.5 bg-white/20 rounded text-[10px] font-bold text-white uppercase tracking-widest hover:bg-white/30 transition-colors cursor-default">
                            {{ \Carbon\Carbon::parse($event->end_date)->format('M d, Y') }}
                        </span>
                    @endif
                </div>
            </div>

        </div>
    </x-slot>

    <div class="py-10 bg-[#f8fafc] overflow-x-hidden">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <section class="mb-8 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col justify-between gap-6 p-6 lg:flex-row lg:items-center lg:p-8">
                    <div class="flex min-w-0 items-center gap-5">
                        @if($event->company?->logo_path)
                            <img src="{{ Storage::url($event->company->logo_path) }}" alt="{{ $event->company->name }} logo" class="h-16 w-16 shrink-0 rounded-2xl border border-slate-200 object-contain p-1.5">
                        @else
                            <div class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-blue-50 text-2xl font-black text-blue-700">{{ strtoupper(substr($event->title, 0, 1)) }}</div>
                        @endif
                        <div class="min-w-0">
                            <p class="truncate text-xs font-extrabold uppercase tracking-[0.18em] text-blue-600">{{ $event->company?->name ?? 'Event workspace' }}</p>
                            <h2 class="mt-2 truncate text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">{{ $event->title }}</h2>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs font-bold text-slate-500">
                                <span>{{ $event->event_date->format('M j, Y') }}</span>
                                @if($event->end_date)<span class="text-slate-300">—</span><span>{{ $event->end_date->format('M j, Y') }}</span>@endif
                                @if($event->location)<span class="text-slate-300">·</span><span>{{ $event->location }}</span>@endif
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 lg:justify-end">
                        @can('scanAttendance', $event)
                            <a href="{{ route('events.scanner', $event) }}" class="rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-extrabold text-white shadow-sm hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">Open QR scanner</a>
                            <a href="{{ URL::signedRoute('scan.events', ['event' => $event->id]) }}" target="_blank" class="rounded-xl bg-cyan-100 px-4 py-2.5 text-xs font-extrabold text-cyan-900 hover:bg-cyan-200">Phone check-in page</a>
                        @endcan
                        @can('manageWhenOpen', $event)
                            <button onclick="document.getElementById('importModal').classList.remove('hidden')" class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-extrabold text-white hover:bg-blue-700">
                                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1m-4-8-4-4m0 0L8 8m4-4v12" /></svg>Import
                            </button>
                        @endcan
                    </div>
                </div>
            </section>
            
            @php $status = $event->status; @endphp

            @if($status === 'active')
                <div data-aos="zoom-in" data-aos-delay="100" class="bg-gradient-to-r from-blue-700 to-blue-600 text-white p-5 mb-8 shadow-xl shadow-blue-100 flex items-center justify-between rounded-2xl border border-blue-400/30">
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
                            <span class="text-xl font-black italic">{{ $event->attendanceSessionLabel($currentDay) }}</span>
                        </div>
                    </div>
                   
                    @can('update', $event)
                        <div class="bg-white/10 p-1.5 rounded-xl border border-white/10 hover:bg-white/20 transition-colors">
                            <x-day-switcher :event="$event" />
                        </div>
                    @endcan
                </div>
            @endif

            <x-event-closed-banner :event="$event" />

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {{-- Left Side Stats --}}
                <div class="lg:col-span-1 space-y-8" data-aos="fade-right" data-aos-delay="200">
                    <div class="rounded-3xl border border-blue-100 bg-white p-8 shadow-sm">
                        <h3 class="text-xs font-black uppercase tracking-widest text-blue-900">Flexible check-in</h3>
                        <p class="mt-3 text-sm leading-6 text-slate-600">Members can scan their personal QR and enter their phone number. Ushers can use the same phone check-in page, the secure QR scanner, or the manual registry.</p>
                    </div>

                    <div class="group bg-blue-900 rounded-3xl p-8 text-white shadow-xl hover:shadow-2xl transition-all duration-300">
                        <h3 class="font-bold text-blue-300 mb-6 text-xs uppercase tracking-widest">Current Stats</h3>
                        <div class="space-y-4">
                            <div class="flex justify-between items-end border-b border-white/10 pb-2">
                                <span class="text-blue-100/70 text-sm">Total Invited</span>
                                <span class="text-2xl font-black">{{ number_format($totalMembers) }}</span>
                            </div>
                            <div class="flex justify-between items-end">
                                <span class="text-blue-100/70 text-sm">Present Today</span>
                                <span class="text-2xl font-black text-green-400">{{ number_format($presentCount) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right Side Search --}}
                <div class="lg:col-span-2" data-aos="fade-left" data-aos-delay="300">
                    <div class="bg-white shadow-sm border border-gray-100 rounded-3xl overflow-hidden">
                        <div class="bg-gray-50/50 p-6 border-b border-gray-100">
                            <h3 class="font-black text-gray-900 tracking-tight uppercase text-xs">Manual Registry</h3>
                        </div>
                        <div class="p-6">
                            <livewire:attendance-search :event="$event" :day="$currentDay" :key="'attendance-search-'.$currentDay" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. IMPORT MODAL (Placed at the bottom) --}}
    @can('update', $event)
    <div id="importModal" class="fixed inset-0 z-[60] hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity" onclick="document.getElementById('importModal').classList.add('hidden')"></div>

            <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-100">
                <form action="{{ route('events.import.store', $event) }}" method="POST" enctype="multipart/form-data" class="p-8">
                    @csrf
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-blue-50 mb-4">
                            <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-black text-gray-900 mb-2">Import Participants</h3>
                        <p class="text-sm text-gray-500 mb-6">Select your Excel file to bulk-add members.</p>
                    </div>

                    <div class="mb-6">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Select Excel File</label>
                        <input type="file" name="file" required
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-black file:uppercase file:tracking-widest file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-all border border-gray-100 rounded-2xl p-2">
                    </div>

                    <div class="flex flex-col gap-3">
                        <button type="submit" class="w-full bg-blue-600 text-white font-black py-4 rounded-2xl shadow-lg hover:bg-blue-700 transition-all uppercase text-xs tracking-widest">
                            Start Import
                        </button>
                        <button type="button" onclick="document.getElementById('importModal').classList.add('hidden')" class="w-full text-gray-400 font-bold py-2 text-xs uppercase tracking-widest">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endcan
</x-app-layout>
