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
                            <h2 class="mt-2 line-clamp-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">{{ $event->title }}</h2>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs font-bold text-slate-500">
                                <span>{{ $event->event_date->format('M j, Y') }}</span>
                                @if($event->end_date)<span class="text-slate-300">—</span><span>{{ $event->end_date->format('M j, Y') }}</span>@endif
                                @if($event->location)<span class="text-slate-300">·</span><span>{{ $event->location }}</span>@endif
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 lg:justify-end">
                        @can('scanAttendance', $event)
                            <a href="{{ route('events.scanner', $event) }}" data-tour="event-attendance-scanner" class="rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-extrabold text-white shadow-sm hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">Open QR scanner</a>
                            @unless($event->has_arrival_session)
                            <a href="{{ URL::signedRoute('scan.events', ['event' => $event->slug]) }}" target="_blank" class="rounded-xl bg-cyan-100 px-4 py-2.5 text-xs font-extrabold text-cyan-900 hover:bg-cyan-200">Phone check-in page</a>
                            @endunless
                        @endcan
                        @can('manageWhenOpen', $event)
                            @php
                                $recentImports = $event->participantImports()->with('user:id,name')->latest('id')->limit(5)->get();
                                $undoableImports = $event->participantImports()->whereNull('undone_at')->count();
                            @endphp
                            <button onclick="document.getElementById('importModal').classList.remove('hidden')" class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-extrabold text-white hover:bg-blue-700">
                                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1m-4-8-4-4m0 0L8 8m4-4v12" /></svg>Import
                            </button>
                            @if($recentImports->isNotEmpty())
                                {{-- Past imports, with a count while any can still be undone. --}}
                                <a href="{{ route('events.imports', $event) }}" data-imports-link class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-extrabold text-slate-700 hover:bg-slate-100">
                                    Imports
                                    @if($undoableImports > 0)<span class="ml-1.5 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] text-amber-800">{{ $undoableImports }} can be undone</span>@endif
                                </a>
                            @endif
                        @endcan
                        @can('update', $event)
                            <a href="{{ route('events.sessions.index', $event) }}" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-extrabold text-slate-700 hover:bg-slate-100">Manage sessions</a>
                            <form method="POST" action="{{ route('events.cache.clear', $event) }}">
                                @csrf
                                <button class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-extrabold text-slate-700 hover:bg-slate-100" onclick="return confirm('Clear cached totals for this event?')">
                                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v6h6M20 20v-6h-6M5.5 15a7 7 0 0 0 11.7 2.5L20 14M4 10l2.8-3.5A7 7 0 0 1 18.5 9" /></svg>
                                    Clear cache
                                </button>
                            </form>
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
                            <span class="text-xl font-black italic">{{ $event->attendanceSessionLabel($currentDay, $currentSession) }}</span>
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
                    <div data-tour="event-attendance-stats" class="rounded-3xl border border-blue-100 bg-white p-8 shadow-sm">
                        <h3 class="text-xs font-black uppercase tracking-widest text-blue-900">Daily attendance</h3>
                        <p class="mt-3 text-sm leading-6 text-slate-600">{{ $event->has_arrival_session ? 'Only members who completed Arrival check-in appear here. Mark their attendance separately for each program day.' : 'Members can use phone or QR check-in, while staff can use the scanner or manual registry.' }}</p>
                    </div>

                    <livewire:attendance-stats :event="$event" :day="$currentDay" :session="$currentSession" :key="'attendance-stats-'.$currentDay.'-'.$currentSession" />
                </div>

                {{-- Right Side Search --}}
                <div class="lg:col-span-2" data-aos="fade-left" data-aos-delay="300">
                    <div data-no-lift data-tour="event-attendance-manual" class="bg-white shadow-sm border border-gray-100 rounded-3xl overflow-hidden">
                        <div class="bg-gray-50/50 p-6 border-b border-gray-100">
                            <h3 class="font-black text-gray-900 tracking-tight uppercase text-xs">Manual Registry</h3>
                        </div>
                        <div class="p-6">
                            <livewire:attendance-search :event="$event" :day="$currentDay" :session="$currentSession" :key="'attendance-search-'.$currentDay.'-'.$currentSession" />
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
                        <p class="text-sm text-gray-500 mb-6">Select an Excel, CSV, or text-based PDF table to bulk-add members.</p>
                    </div>

                    <div class="mb-6 rounded-2xl border border-gray-100 bg-gray-50 p-4 text-left">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Columns</p>
                        <p class="text-xs leading-6 text-gray-600 mb-2">Put headings in the first row, in any order: <strong>Name</strong> <span class="text-gray-400">(required)</span>, Gender, Room group or Area, Category, Phone, Email, Country.</p>
                        <p class="text-xs leading-6 text-gray-600">Without headings, columns are read in this order:
                            <strong>A</strong> ID &nbsp;·&nbsp; <strong>B</strong> Name <span class="text-gray-400">(required)</span> &nbsp;·&nbsp; <strong>C</strong> Gender &nbsp;·&nbsp; <strong>D</strong> Room group <span class="text-gray-400">(people sharing this value are roomed together where possible)</span> &nbsp;·&nbsp; <strong>E</strong> Category &nbsp;·&nbsp; <strong>F</strong> Phone &nbsp;·&nbsp; <strong>G</strong> Email
                        </p>
                    </div>

                    <div class="mb-6">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Select participant list</label>
                        <input type="file" name="file" required
                               accept=".xlsx,.xls,.csv,.pdf"
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-black file:uppercase file:tracking-widest file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-all border border-gray-100 rounded-2xl p-2">
                    </div>

                    <label class="mb-6 flex cursor-pointer items-start gap-3 rounded-2xl border border-gray-100 bg-gray-50 p-4">
                        <input type="checkbox" name="send_notifications" value="1" @checked(old('send_notifications'))
                               class="mt-0.5 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                        <span>
                            <span class="block text-sm font-black text-gray-900">Send email and SMS after import</span>
                            <span class="mt-1 block text-xs leading-5 text-gray-500">Only newly registered participants in this file will be notified. Leave this unchecked to import without sending messages.</span>
                        </span>
                    </label>

                    @if($event->accommodation_enabled)
                        <label class="mb-6 flex cursor-pointer items-start gap-3 rounded-2xl border border-gray-100 bg-gray-50 p-4">
                            <input type="checkbox" name="needs_room" value="1" @checked(old('needs_room'))
                                   class="mt-0.5 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                            <span>
                                <span class="block text-sm font-black text-gray-900">These attendees need a room</span>
                                <span class="mt-1 block text-xs leading-5 text-gray-500">Marks only newly registered participants as needing a room. If attendees are notified above, their message includes a link to pick one.</span>
                            </span>
                        </label>
                    @endif

                    <div class="flex flex-col gap-3">
                        <button type="submit" class="w-full bg-blue-600 text-white font-black py-4 rounded-2xl shadow-lg hover:bg-blue-700 transition-all uppercase text-xs tracking-widest">
                            Start Import
                        </button>
                        <button type="button" onclick="document.getElementById('importModal').classList.add('hidden')" class="w-full text-gray-400 font-bold py-2 text-xs uppercase tracking-widest">
                            Cancel
                        </button>
                    </div>
                </form>
                @can('manageWhenOpen', $event)
                    @if($recentImports->isNotEmpty())
                        {{-- Recent imports, newest first, each undoable once. --}}
                        <div class="border-t border-gray-100 px-8 pb-8 pt-6">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-400">Recent imports</h4>
                                <a href="{{ route('events.imports', $event) }}" class="text-xs font-bold text-blue-700 hover:text-blue-900">See all imports</a>
                            </div>
                            <ul class="space-y-3">
                                @foreach($recentImports as $recent)
                                    <li class="flex items-center justify-between gap-3 rounded-2xl border border-gray-100 bg-gray-50 p-3">
                                        <div class="min-w-0 text-xs text-gray-600">
                                            <p class="truncate font-black text-gray-900">{{ $recent->file_name }}</p>
                                            <p>{{ $recent->created_at->format('j M, g:i a') }}@if($recent->user) · {{ $recent->user->name }}@endif · {{ $recent->addedToEventCount() }} added</p>
                                        </div>
                                        @if($recent->undone_at)
                                            <span class="shrink-0 text-xs font-bold text-gray-400">Undone {{ $recent->undone_at->format('j M, g:i a') }}</span>
                                        @else
                                            @include('events.partials.undo-import', ['import' => $recent])
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endcan
            </div>
        </div>
    </div>

    @php($importReview = session('participant-import-review.'.$event->id))
    @if($importReview)
        {{-- Rows of an uploaded list that would rename someone already registered; nothing is saved until the manager confirms. --}}
        <div id="importReviewModal" x-data="{ open: true }" x-show="open" class="fixed inset-0 z-[70] overflow-y-auto">
            <div class="flex min-h-screen items-center justify-center px-4 py-8">
                <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm"></div>
                <form action="{{ route('events.import.confirm', $event) }}" method="POST" x-ref="form"
                      class="relative w-full max-w-4xl rounded-3xl border border-gray-100 bg-white p-6 text-left shadow-2xl sm:p-8">
                    @csrf
                    <h3 class="text-xl font-black text-gray-900">Check {{ count($importReview['rows']) }} {{ Str::plural('row', count($importReview['rows'])) }} before importing</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        In <strong>{{ $importReview['file_name'] }}</strong>, these rows share a phone or email with someone already registered under a different name.
                        Choose <strong>Update</strong> if it's the same person, <strong>New person</strong> to keep both, or <strong>Skip</strong> to leave the row out.
                        Nothing has been imported yet.
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2 text-xs font-black uppercase tracking-widest">
                        <button type="button" class="rounded-xl bg-blue-50 px-3 py-2 text-blue-700 hover:bg-blue-100"
                                @click="$refs.form.querySelectorAll('input[value=update]').forEach(i => i.checked = true)">Update all</button>
                        <button type="button" class="rounded-xl bg-gray-100 px-3 py-2 text-gray-700 hover:bg-gray-200"
                                @click="$refs.form.querySelectorAll('input[value=new]').forEach(i => i.checked = true)">All new people</button>
                        <button type="button" class="rounded-xl bg-gray-100 px-3 py-2 text-gray-700 hover:bg-gray-200"
                                @click="$refs.form.querySelectorAll('input[value=skip]').forEach(i => i.checked = true)">Skip all</button>
                    </div>

                    <div class="mt-4 max-h-[55vh] overflow-auto rounded-2xl border border-gray-100">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="sticky top-0 bg-gray-50 text-[10px] font-black uppercase tracking-widest text-gray-400">
                                <tr>
                                    <th class="px-3 py-2 text-left">Row</th>
                                    <th class="px-3 py-2 text-left">In your file</th>
                                    <th class="px-3 py-2 text-left">Already registered</th>
                                    <th class="px-3 py-2 text-left">Choice</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($importReview['rows'] as $row)
                                    <tr class="align-top">
                                        <td class="px-3 py-3 font-bold text-gray-400">{{ $row['row'] }}</td>
                                        <td class="px-3 py-3">
                                            <div class="font-bold text-gray-900">{{ $row['name'] }}</div>
                                            <div class="text-xs text-gray-500">{{ $row['phone'] ?: '—' }} · {{ $row['email'] ?: '—' }}</div>
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="font-bold text-gray-900">{{ $row['existing_name'] }}</div>
                                            <div class="text-xs text-gray-500">{{ $row['existing_phone'] ?: '—' }} · {{ $row['existing_email'] ?: '—' }}</div>
                                            <div class="mt-1 text-[11px] font-bold text-amber-700">Same {{ $row['matched_by'] }}</div>
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="flex flex-col gap-1 text-xs font-bold text-gray-700">
                                                @foreach(['update' => 'Update', 'new' => 'New person', 'skip' => 'Skip'] as $value => $label)
                                                    <label class="flex cursor-pointer items-center gap-2">
                                                        <input type="radio" name="decisions[{{ $row['row'] }}]" value="{{ $value }}" @checked($value === 'new')
                                                               class="border-gray-300 text-blue-600 focus:ring-blue-500">
                                                        {{ $label }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-3 text-xs text-gray-500">The other rows in the file are imported as usual.</p>

                    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="submit" form="importReviewCancel" class="rounded-2xl px-5 py-3 text-xs font-black uppercase tracking-widest text-gray-500 hover:text-gray-700">
                            Cancel import
                        </button>
                        <button type="submit" class="rounded-2xl bg-blue-600 px-6 py-3 text-xs font-black uppercase tracking-widest text-white shadow-lg hover:bg-blue-700">
                            Import with these choices
                        </button>
                    </div>
                </form>
                <form id="importReviewCancel" action="{{ route('events.import.cancel', $event) }}" method="POST" class="hidden">@csrf</form>
            </div>
        </div>
    @endif
    @endcan
</x-app-layout>
