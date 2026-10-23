@php use App\Support\AccommodationPriority as Priority; @endphp
<x-app-layout>
    <x-slot name="header">Accommodation</x-slot>
    <div class="py-10"><div class="mx-auto max-w-7xl space-y-7 sm:px-6 lg:px-8">
        <section class="rounded-3xl bg-gradient-to-br from-indigo-700 to-blue-950 p-8 text-white shadow-xl">
            <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
                <div><p class="text-xs font-black uppercase tracking-[.2em] text-indigo-200">Room allocation</p><h1 class="mt-2 text-3xl font-black">{{ $event->title }}</h1><p class="mt-3 max-w-2xl text-sm text-indigo-100">Add your buildings and rooms, mark who needs a bed, preview the result, then assign rooms automatically.</p></div>
                <form method="POST" action="{{ route('events.accommodation.settings', $event) }}" data-tour="event-rooms-settings" class="flex flex-wrap items-center gap-4 rounded-2xl bg-white/10 p-4">@csrf @method('PATCH')
                    <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="accommodation_enabled" value="1" @checked($event->accommodation_enabled)> Use accommodation</label>
                    <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="accommodation_published" value="1" @checked($event->accommodation_published)> Show rooms to attendees</label>
                    <label class="flex items-center gap-2 text-sm font-bold">Let attendees pick until<input type="datetime-local" name="accommodation_self_select_closes_at" value="{{ optional($event->accommodation_self_select_closes_at)->format('Y-m-d\TH:i') }}" class="rounded-lg border-0 bg-white/20 px-2 py-1 text-xs text-white [color-scheme:dark]"></label>
                    <button class="rounded-xl bg-white px-4 py-2 text-sm font-black text-indigo-800">Save</button>
                </form>
            </div>
        </section>

        <livewire:accommodation-stats :event="$event" />

        @if($errors->any())<div class="rounded-2xl bg-red-50 p-4 text-sm font-bold text-red-800">{{ $errors->first() }}</div>@endif

        @php
            $beds = $rooms->sum('capacity');
            $waiting = max(0, $requiredCount - $assignedCount);
            $cards = [
                ['board', route('events.accommodation.board', $event), 'Room board', 'Drag people between rooms on a full-screen board.', $waiting.' waiting · opens in a new tab', 'M4 5h6v6H4zM14 5h6v6h-6zM4 15h6v4H4zM14 15h6v4h-6z', 'from-indigo-500 to-violet-600', null],
                ['assign', route('events.accommodation.section', [$event, 'assign']), 'Assign automatically', 'Place everyone who needs a room in one go.', $waiting.' still to place', 'M13 10V3L4 14h7v7l9-11h-7z', 'from-amber-400 to-orange-500', 'event-rooms-assign'],
                ['room-plan', route('events.accommodation.section', [$event, 'room-plan']), 'Room plan import', 'Upload a list and set how many share a room.', 'Preview before saving', 'M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0-12l-4 4m4-4l4 4', 'from-sky-400 to-blue-600', null],
                ['inventory', route('events.accommodation.section', [$event, 'inventory']), 'Buildings & rooms', 'Locations, floors and rooms; import or copy a setup.', $rooms->count().' rooms · '.$beds.' beds', 'M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6', 'from-emerald-400 to-teal-600', 'event-rooms-inventory'],
                ['guests', route('events.accommodation.section', [$event, 'guests']), 'Who needs a room', 'Mark who needs a bed; place people one by one.', $requiredCount.' need a room · '.$assignedCount.' placed', 'M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87m4-5.13a4 4 0 11-8 0 4 4 0 018 0zm6 2a3 3 0 11-6 0 3 3 0 016 0z', 'from-rose-400 to-pink-600', null],
                ['send', route('events.accommodation.section', [$event, 'send']), 'Send to attendees', 'Email rooms, or let people choose their own.', $event->accommodation_published ? 'Rooms visible to attendees' : 'Rooms hidden from attendees', 'M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'from-fuchsia-500 to-purple-600', null],
                ['report', route('events.accommodation.report', $event), 'Reports', 'Occupancy by building; CSV and PDF downloads.', $assignedCount.' of '.$beds.' beds used', 'M9 17v-6m4 6V7m4 10v-3M5 21h14', 'from-slate-500 to-slate-800', null],
            ];
        @endphp
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($cards as [$key, $url, $title, $text, $stat, $icon, $tone, $tour])
                <a href="{{ $url }}" @if($key === 'board') target="_blank" rel="noopener" @endif @if($tour) data-tour="{{ $tour }}" @endif
                   class="group relative flex flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-indigo-200 hover:shadow-xl">
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-gradient-to-br {{ $tone }} text-white shadow-lg">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    </span>
                    <span class="mt-5 flex items-center gap-2 text-lg font-black text-slate-900">{{ $title }}
                        @if($key === 'board')<svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 4h6v6M20 4l-9 9M19 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1h5"/></svg>@endif
                    </span>
                    <span class="mt-1 text-sm text-slate-500">{{ $text }}</span>
                    <span class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4 text-xs font-bold text-slate-500">{{ $stat }}
                        <span class="text-indigo-600 transition group-hover:translate-x-1">Open →</span></span>
                </a>
            @endforeach
        </div>
    </div></div>
</x-app-layout>
