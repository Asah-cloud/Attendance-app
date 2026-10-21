<x-app-layout>
    <x-slot name="header">Room board</x-slot>
    <div data-room-board data-move-url="{{ route('events.accommodation.board.move', $event) }}" class="space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900">Room board</h1>
                <p class="mt-1 text-sm text-slate-500">Drag people between rooms, or back to "Needs a room". Every move is saved straight away.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <input type="search" data-board-search placeholder="Find a person, area or phone" class="w-64 rounded-xl border-slate-200 text-sm">
                <span class="flex items-center gap-1.5 text-xs font-bold text-slate-500"><span class="h-3 w-3 rounded bg-sky-200"></span>Male <span class="ml-2 h-3 w-3 rounded bg-pink-200"></span>Female</span>
                <a href="{{ route('events.accommodation.index', $event) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-black text-slate-700">Back to accommodation</a>
            </div>
        </div>

        <div class="grid items-start gap-5 lg:grid-cols-[18rem_1fr]">
            <aside class="rounded-3xl border border-amber-200 bg-amber-50 p-4 lg:sticky lg:top-24">
                <h2 class="flex items-center justify-between font-black text-amber-900">Needs a room <span data-count-for="" class="rounded-full bg-amber-200 px-2.5 py-0.5 text-xs">{{ $waiting->count() }}</span></h2>
                <ul data-room-list data-room-id="" class="mt-3 max-h-[70vh] min-h-24 space-y-2 overflow-y-auto pr-1">
                    @foreach($waiting as $registration)
                        @include('accommodation._board-chip', ['registration' => $registration])
                    @endforeach
                </ul>
                <p class="mt-3 text-[11px] text-amber-800">Only people marked as needing a room are listed here.</p>
            </aside>

            <div class="space-y-6">
                @forelse($sites as $site)
                    @foreach($site->blocks as $block)
                        @foreach($block->floors as $floor)
                            @continue($floor->rooms->isEmpty())
                            <section>
                                <h2 class="mb-3 text-sm font-black uppercase tracking-wider text-slate-500">{{ $site->name }} · {{ $block->name }} · {{ $floor->name }}
                                    @if($block->gender_restriction)<span class="ml-1 rounded-full bg-slate-200 px-2 py-0.5 text-[10px] text-slate-600">{{ $block->gender_restriction }} only</span>@endif</h2>
                                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                    @foreach($floor->rooms as $room)
                                        @php($used = $room->activeAssignments->count())
                                        <div data-room-card class="{{ $used >= $room->capacity ? 'is-full' : '' }} group rounded-2xl border border-slate-200 bg-white p-3 shadow-sm [&.is-full]:border-slate-300 [&.is-full]:bg-slate-50">
                                            <div class="mb-2 flex items-center justify-between gap-2">
                                                <p class="font-black text-slate-900">{{ $room->name }}
                                                    @if($room->gender_restriction)<span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500">{{ $room->gender_restriction }}</span>@endif
                                                    @if($room->status === 'reserved')<span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">Reserved</span>@endif</p>
                                                <span data-count-for="{{ $room->id }}" class="text-xs font-bold text-slate-500 group-[.is-full]:text-rose-600">{{ $used }} / {{ $room->capacity }} beds</span>
                                            </div>
                                            <ul data-room-list data-room-id="{{ $room->id }}" data-room-name="{{ $room->name }}" data-capacity="{{ $room->capacity }}" class="min-h-16 space-y-2 rounded-xl border border-dashed border-slate-200 p-2">
                                                @foreach($room->activeAssignments->sortBy(fn ($assignment) => $assignment->registration->participant->name) as $assignment)
                                                    @include('accommodation._board-chip', ['registration' => $assignment->registration->setRelation('roomAssignment', $assignment)])
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    @endforeach
                @empty
                    <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">No rooms yet. Add buildings and rooms on the Accommodation page first.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
