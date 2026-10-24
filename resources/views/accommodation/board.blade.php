{{-- The room board: a full-screen page of its own (opened in a new tab from Accommodation). --}}
@php
    $rooms = $sites->flatMap->blocks->flatMap->floors->flatMap->rooms;
    $placed = $rooms->sum(fn ($room) => $room->activeAssignments->count());
    $freeBeds = max(0, $rooms->sum('capacity') - $placed);
    $buildings = $sites->flatMap(fn ($site) => $site->blocks->map(fn ($block) => ['id' => $block->id, 'name' => $block->name, 'site' => $site->name]));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Room board · {{ $event->title }}</title>
    <x-site-icons />
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap" rel="stylesheet">
    <x-compiled-assets />
    <style>
        [x-cloak] { display: none !important; }
        /* Folded (full, not opened) rooms and compact view: people as a row of initials. */
        [data-room-card].is-full:not(.is-open) [data-room-list],
        [data-density="compact"] [data-room-card]:not(.is-open) [data-room-list] { display: flex; flex-wrap: wrap; gap: .375rem; min-height: 3.25rem; }
        [data-room-card].is-full:not(.is-open) [data-room-list] > *,
        [data-density="compact"] [data-room-card]:not(.is-open) [data-room-list] > * { margin: 0 !important; }
        [data-room-card].is-full:not(.is-open) .board-chip,
        [data-density="compact"] [data-room-card]:not(.is-open) .board-chip { padding: 0; border: 0; background: transparent; box-shadow: none; }
        [data-room-card].is-full:not(.is-open) .chip-text, [data-room-card].is-full:not(.is-open) .chip-badge,
        [data-density="compact"] [data-room-card]:not(.is-open) .chip-text, [data-density="compact"] [data-room-card]:not(.is-open) .chip-badge { display: none; }
        /* Room filter: hide full rooms, or rooms with space. */
        [data-show="space"] [data-room-card].is-full, [data-show="full"] [data-room-card]:not(.is-full), [data-floor].is-empty { display: none; }
        /* Where a dragged or picked person would land. */
        [data-drop-zone] { transition: box-shadow .12s, transform .12s; }
        [data-drop-zone].is-drop-target { box-shadow: 0 0 0 3px rgb(52 211 153 / .9); transform: scale(1.01); }
        [data-drop-zone].is-drop-blocked { box-shadow: 0 0 0 3px rgb(251 113 133 / .8); }
        .is-placing [data-room-card]:not(.is-full), .is-placing aside[data-drop-zone] { cursor: pointer; box-shadow: 0 0 0 2px rgb(52 211 153 / .45); }
        .is-placing [data-room-card]:not(.is-full):hover { box-shadow: 0 0 0 3px rgb(52 211 153 / .95); }
        .board-chip.is-selected { outline: 3px solid rgb(252 211 77); outline-offset: 2px; }
        .is-dragging, .is-dragging * { cursor: grabbing !important; }
    </style>
</head>
<body class="min-h-full bg-[#0b1026] text-white antialiased" style="font-family: Manrope, sans-serif">
    <x-toast />
    {{-- Soft colour glows behind everything. --}}
    <div aria-hidden="true" class="pointer-events-none fixed inset-0 overflow-hidden">
        <div class="absolute -left-40 -top-40 h-[34rem] w-[34rem] rounded-full bg-indigo-600/30 blur-3xl"></div>
        <div class="absolute -right-32 top-1/4 h-[30rem] w-[30rem] rounded-full bg-fuchsia-600/20 blur-3xl"></div>
        <div class="absolute bottom-0 left-1/3 h-[26rem] w-[26rem] rounded-full bg-sky-500/20 blur-3xl"></div>
        <div class="absolute inset-0 bg-[radial-gradient(rgb(255_255_255_/_0.05)_1px,transparent_1px)] [background-size:24px_24px]"></div>
    </div>

    <div data-room-board data-show="all" data-density="names" data-move-url="{{ route('events.accommodation.board.move', $event) }}" x-data="{ building: 'all' }" x-effect="building; $nextTick(() => window.dispatchEvent(new Event('room-board:filter')))" class="relative">
        <header class="z-30 border-b lg:sticky lg:top-0 border-white/10 bg-[#0b1026]/70 backdrop-blur-xl">
            <div class="mx-auto flex max-w-[1600px] flex-wrap items-center gap-4 px-5 py-4 lg:px-8">
                <div class="min-w-0 basis-full sm:basis-auto sm:flex-1">
                    <p class="text-[11px] font-black uppercase tracking-[0.25em] text-indigo-300">Room board</p>
                    <h1 class="truncate text-xl font-black sm:text-2xl">{{ $event->title }}</h1>
                </div>
                <div class="flex gap-2">
                    <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-center"><p data-count-for="" class="text-xl font-black text-amber-300">{{ $waiting->count() }}</p><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Waiting</p></div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-center"><p data-total-placed class="text-xl font-black text-emerald-300">{{ $placed }}</p><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Placed</p></div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-center"><p data-total-free class="text-xl font-black text-sky-300">{{ $freeBeds }}</p><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Free beds</p></div>
                </div>
                <a href="{{ route('events.accommodation.index', $event) }}" class="rounded-xl border border-white/15 px-4 py-2 text-sm font-bold text-slate-200 transition hover:bg-white/10">Accommodation</a>
            </div>
            <div class="mx-auto flex max-w-[1600px] flex-wrap items-center gap-3 px-5 pb-4 lg:px-8">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z"/></svg>
                    <input type="search" data-board-search placeholder="Find a person, area or phone" class="w-72 rounded-xl border-white/10 bg-white/10 py-2 pl-9 text-sm text-white placeholder-slate-400 focus:border-indigo-400 focus:ring-indigo-400">
                </div>
                <div class="flex flex-wrap gap-1.5 text-xs font-bold">
                    <button type="button" @click="building = 'all'" :class="building === 'all' ? 'bg-white text-slate-900' : 'bg-white/10 text-slate-200 hover:bg-white/20'" class="rounded-full px-3 py-1.5 transition">All buildings</button>
                    @foreach($buildings as $building)
                        <button type="button" @click="building = '{{ $building['id'] }}'" :class="building === '{{ $building['id'] }}' ? 'bg-white text-slate-900' : 'bg-white/10 text-slate-200 hover:bg-white/20'" class="rounded-full px-3 py-1.5 transition">{{ $building['name'] }}</button>
                    @endforeach
                </div>
                <div class="flex flex-wrap gap-1.5 text-xs font-bold" role="group" aria-label="Which rooms to show">
                    @foreach(['all' => 'All rooms', 'space' => 'Has space', 'full' => 'Full'] as $key => $label)
                        <button type="button" data-show-button="{{ $key }}" class="rounded-full bg-white/10 px-3 py-1.5 text-slate-200 transition hover:bg-white/20 [&.is-active]:bg-amber-300 [&.is-active]:text-slate-900">{{ $label }} <span data-show-count="{{ $key }}" class="opacity-70"></span></button>
                    @endforeach
                </div>
                <button type="button" data-density-toggle class="rounded-full border border-white/15 px-3 py-1.5 text-xs font-bold text-slate-200 transition hover:bg-white/10">Compact view</button>
                <span class="ml-auto flex items-center gap-3 text-xs font-bold text-slate-300">
                    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-gradient-to-br from-sky-400 to-blue-600"></span>Male</span>
                    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-gradient-to-br from-pink-400 to-rose-500"></span>Female</span>
                </span>
            </div>
        </header>

        {{-- Shown while a person is picked by tapping. --}}
        <div data-placing-banner style="display: none" class="fixed inset-x-4 bottom-4 z-40 mx-auto flex max-w-xl items-center justify-between gap-3 rounded-2xl bg-amber-300 px-4 py-3 text-sm font-bold text-slate-900 shadow-2xl shadow-black/40">
            <span>Placing <span data-placing-name></span>: tap a room with a green outline.</span>
            <button type="button" data-placing-cancel class="rounded-lg bg-slate-900/10 px-3 py-1 text-xs font-black hover:bg-slate-900/20">Cancel</button>
        </div>

        <div class="mx-auto grid max-w-[1600px] items-start gap-6 px-5 py-6 lg:grid-cols-[20rem_1fr] lg:px-8">
            <aside data-drop-zone class="rounded-3xl border border-amber-300/20 bg-amber-300/10 p-4 backdrop-blur-xl lg:sticky lg:top-40">
                <h2 class="font-black text-amber-200">Needs a room</h2>
                <p class="mt-0.5 text-xs text-amber-100/70">Drag someone onto any part of a room, or tap them and then tap a room.</p>
                <ul data-room-list data-room-id="" class="mt-3 max-h-[calc(100vh-16rem)] min-h-24 space-y-2 overflow-y-auto pr-1">
                    @foreach($waiting as $registration)
                        @include('accommodation._board-chip', ['registration' => $registration])
                    @endforeach
                </ul>
            </aside>

            <main class="space-y-8">
                @forelse($sites as $site)
                    @foreach($site->blocks as $block)
                        <section x-show="building === 'all' || building === '{{ $block->id }}'" class="space-y-4">
                            <div class="flex flex-wrap items-baseline gap-3">
                                <h2 class="text-lg font-black">{{ $block->name }}</h2>
                                <span class="text-xs font-bold text-slate-400">{{ $site->name }}</span>
                                @if($block->gender_restriction)<span class="rounded-full bg-white/10 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-slate-300">{{ $block->gender_restriction }} only</span>@endif
                            </div>
                            @foreach($block->floors as $floor)
                                @continue($floor->rooms->isEmpty())
                                <div data-floor>
                                    <p class="mb-2 text-[11px] font-black uppercase tracking-[0.2em] text-slate-500">{{ $floor->name }}</p>
                                    <div class="grid items-start gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                                        @foreach($floor->rooms as $room)
                                            @php
                                                $used = $room->activeAssignments->count();
                                            @endphp
                                            <div data-room-card data-drop-zone class="{{ $used >= $room->capacity ? 'is-full' : '' }} group rounded-3xl border border-white/10 bg-white/[0.06] p-4 shadow-xl shadow-black/20 backdrop-blur-xl transition [&.is-full]:border-rose-400/30">
                                                <div class="flex items-center justify-between gap-2">
                                                    <p class="text-base font-black">{{ $room->name }}
                                                        @if($room->gender_restriction)<span class="ml-1 rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-bold text-slate-300">{{ $room->gender_restriction }}</span>@endif
                                                        @if($room->status === 'reserved')<span class="ml-1 rounded-full bg-amber-400/20 px-2 py-0.5 text-[10px] font-bold text-amber-200">Reserved</span>@endif</p>
                                                    <span class="flex shrink-0 items-center gap-2">
                                                        <span data-count-for="{{ $room->id }}" class="text-xs font-bold text-slate-300 group-[.is-full]:text-rose-300">{{ $used }} / {{ $room->capacity }} beds</span>
                                                        <button type="button" data-room-toggle class="hidden rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-bold text-slate-200 hover:bg-white/20 group-[.is-full]:inline-block group-[.is-compact]:inline-block" aria-expanded="false">Show names</button>
                                                    </span>
                                                </div>
                                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10">
                                                    <div data-bar-for="{{ $room->id }}" class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-teal-400 transition-all duration-300 group-[.is-full]:from-rose-400 group-[.is-full]:to-pink-500" style="width: {{ $room->capacity ? min(100, round($used / $room->capacity * 100)) : 0 }}%"></div>
                                                </div>
                                                <ul data-room-list data-room-id="{{ $room->id }}" data-room-name="{{ $room->name }}" data-capacity="{{ $room->capacity }}" class="mt-3 max-h-80 min-h-16 space-y-2 overflow-y-auto rounded-2xl border border-dashed border-white/15 p-2">
                                                    @foreach($room->activeAssignments->sortBy(fn ($assignment) => $assignment->registration->participant->name) as $assignment)
                                                        @include('accommodation._board-chip', ['registration' => $assignment->registration->setRelation('roomAssignment', $assignment)])
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </section>
                    @endforeach
                @empty
                    <div class="rounded-3xl border border-dashed border-white/20 bg-white/5 p-12 text-center text-sm text-slate-300">No rooms yet. Add buildings and rooms on the Accommodation page first.</div>
                @endforelse
            </main>
        </div>
    </div>
</body>
</html>
