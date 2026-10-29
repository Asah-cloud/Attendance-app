@php use App\Support\AccommodationPriority as Priority; @endphp
<x-app-layout>
    <x-slot name="header">Room plan import</x-slot>
    <div class="py-10"><div class="mx-auto max-w-7xl space-y-7 sm:px-6 lg:px-8">
        @include('accommodation.sections._header', ['title' => 'Room plan import', 'intro' => 'Place a listed group a set number to a room, registering anyone who is not registered yet.'])
        @if($errors->any())<div class="rounded-2xl bg-red-50 p-4 text-sm font-bold text-red-800">{{ $errors->first() }}</div>@endif
        @php
            $planSites = $event->accommodationSites()->with('blocks.floors.rooms')->orderBy('name')->get();
        @endphp
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black text-slate-900">Room plan import</h2>
            <p class="mt-1 text-sm text-slate-500">Upload a list (same columns as the participant import) and choose how many of them share a room, even where rooms have more beds. Spare beds stay free for others. Anyone on the list who isn't registered for this event yet will be registered. You'll see a preview before anything is saved.</p>
            <p class="mt-2 text-sm text-slate-500">To put someone in a particular room, add a <strong>Room</strong> column with the room's name (for example NB-204). They go straight to that room; everyone else is placed in the rooms you choose below.</p>
            <form method="POST" enctype="multipart/form-data" action="{{ route('events.accommodation.plan.upload', $event) }}" class="mt-4 space-y-3 rounded-2xl bg-slate-50 p-4" data-room-plan>@csrf
                <div class="grid gap-3 md:grid-cols-[2fr_1fr_1.5fr] md:items-end">
                    <div><label class="text-xs font-black uppercase text-slate-500">List</label><input type="file" name="file" required accept=".xlsx,.xls,.csv,.pdf" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white p-2 text-sm"></div>
                    <div><label class="text-xs font-black uppercase text-slate-500">People per room</label><input type="number" name="per_room" min="1" max="20" value="{{ old('per_room', 3) }}" required class="mt-2 w-full rounded-xl border-slate-200"></div>
                    <div><label class="text-xs font-black uppercase text-slate-500">Where</label>
                        <select name="location" class="mt-2 w-full rounded-xl border-slate-200">
                            <option value="">Any room</option>
                            @foreach($planSites as $site)
                                <option value="site:{{ $site->id }}" @selected(old('location') === 'site:'.$site->id)>{{ $site->name }} (all buildings)</option>
                                @foreach($site->blocks as $block)
                                    <option value="block:{{ $block->id }}" @selected(old('location') === 'block:'.$block->id)>&nbsp;&nbsp;{{ $block->name }} (all floors)</option>
                                    @foreach($block->floors as $floor)<option value="floor:{{ $floor->id }}" @selected(old('location') === 'floor:'.$floor->id)>&nbsp;&nbsp;&nbsp;&nbsp;{{ $block->name }} · {{ $floor->name }}</option>@endforeach
                                @endforeach
                            @endforeach
                        </select>
                        <input type="hidden" name="site_id"><input type="hidden" name="block_id"><input type="hidden" name="floor_id">
                    </div>
                </div>
                <div>
                    <label class="text-xs font-black uppercase text-slate-500" for="plan-rooms">Only these rooms <span class="font-bold normal-case text-slate-400">(optional)</span></label>
                    <input id="plan-rooms" type="text" name="rooms" value="{{ old('rooms') }}" placeholder="e.g. 101-110, 115 or NB-201 to NB-210" class="mt-2 w-full rounded-xl border-slate-200 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Leave empty to use every room in the place chosen above. Room numbers, names and ranges work; chosen rooms fill lowest number first.</p>
                    <details class="mt-2">
                        <summary class="cursor-pointer text-xs font-bold text-indigo-700">Or tick rooms</summary>
                        <div class="mt-2 max-h-72 space-y-3 overflow-y-auto rounded-xl border border-slate-200 bg-white p-3">
                            @forelse($planSites as $site)
                                @foreach($site->blocks as $block)
                                    @foreach($block->floors as $floor)
                                        <div data-room-floor data-site="{{ $site->id }}" data-block="{{ $block->id }}" data-floor="{{ $floor->id }}">
                                            <p class="text-xs font-black text-slate-700">{{ $block->name }} · {{ $floor->name }}</p>
                                            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                                                @foreach($floor->rooms->sort(fn ($a, $b) => strnatcasecmp($a->name, $b->name)) as $room)
                                                    <label class="flex items-center gap-1.5 text-xs text-slate-700"><input type="checkbox" name="room_ids[]" value="{{ $room->id }}" @checked(in_array((string) $room->id, old('room_ids', []), true)) class="rounded border-slate-300 text-indigo-600"> {{ $room->name }} <span class="text-slate-400">({{ $room->capacity }})</span></label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                @endforeach
                            @empty
                                <p class="text-xs text-slate-500">No rooms have been set up yet.</p>
                            @endforelse
                        </div>
                    </details>
                </div>
                <div class="flex justify-end"><button class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-black text-white" @disabled(!$event->accommodation_enabled)>Preview plan</button></div>
            </form>
            <script>
                (() => {
                    // "Where" fills the hidden ids, and the tick list shows only that place's floors.
                    const form = document.querySelector('[data-room-plan]');
                    const select = form.elements.location;
                    const apply = () => {
                        const [type, id] = select.value.split(':');
                        for (const key of ['site', 'block', 'floor']) form.elements[key + '_id'].value = type === key ? id : '';
                        form.querySelectorAll('[data-room-floor]').forEach((group) => {
                            const shown = !type || group.dataset[type] === id;
                            group.hidden = !shown;
                            if (!shown) group.querySelectorAll('input').forEach((box) => { box.checked = false; });
                        });
                    };
                    select.addEventListener('change', apply);
                    apply();
                })();
            </script>
        </section>
    </div></div>
</x-app-layout>
