@php use App\Support\AccommodationPriority as Priority; @endphp
<x-app-layout>
    <x-slot name="header">Accommodation</x-slot>
    <div class="py-10"><div class="mx-auto max-w-7xl space-y-7 sm:px-6 lg:px-8">
        <section class="rounded-3xl bg-gradient-to-br from-indigo-700 to-blue-950 p-8 text-white shadow-xl">
            <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
                <div><p class="text-xs font-black uppercase tracking-[.2em] text-indigo-200">Room allocation</p><h1 class="mt-2 text-3xl font-black">{{ $event->title }}</h1><p class="mt-3 max-w-2xl text-sm text-indigo-100">Add your buildings and rooms, mark who needs a bed, preview the result, then assign rooms automatically.</p></div>
                <form method="POST" action="{{ route('events.accommodation.settings', $event) }}" class="flex flex-wrap items-center gap-4 rounded-2xl bg-white/10 p-4">@csrf @method('PATCH')
                    <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="accommodation_enabled" value="1" @checked($event->accommodation_enabled)> Use accommodation</label>
                    <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="accommodation_published" value="1" @checked($event->accommodation_published)> Show rooms to attendees</label>
                    <label class="flex items-center gap-2 text-sm font-bold">Let attendees pick until<input type="datetime-local" name="accommodation_self_select_closes_at" value="{{ optional($event->accommodation_self_select_closes_at)->format('Y-m-d\TH:i') }}" class="rounded-lg border-0 bg-white/20 px-2 py-1 text-xs text-white [color-scheme:dark]"></label>
                    <button class="rounded-xl bg-white px-4 py-2 text-sm font-black text-indigo-800">Save</button>
                </form>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-4">
            <x-summary-card title="Beds" :value="$rooms->sum('capacity')" color="blue" />
            <x-summary-card title="Rooms" :value="$rooms->count()" color="green" />
            <x-summary-card title="Need rooms" :value="$requiredCount" color="amber" />
            <x-summary-card title="Allocated" :value="$assignedCount" color="purple" />
        </div>

        @if(session('success'))<div class="rounded-2xl bg-emerald-50 p-4 text-sm font-bold text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-2xl bg-red-50 p-4 text-sm font-bold text-red-800">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="rounded-2xl bg-red-50 p-4 text-sm font-bold text-red-800">{{ $errors->first() }}</div>@endif

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4"><div><h2 class="text-xl font-black text-slate-900">Assign rooms automatically</h2><p class="mt-1 text-sm text-slate-500">People who need a step-free room are placed first. Rooms then fill in the priority order you set.</p></div><div class="flex flex-wrap gap-2">
                <a href="{{ route('events.accommodation.report.csv', $event) }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-700">CSV report</a>
                <a href="{{ route('events.accommodation.report.pdf', $event) }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-700">PDF report</a>
                <form method="POST" action="{{ route('events.accommodation.notify', $event) }}">@csrf<button class="rounded-xl border border-indigo-200 px-4 py-2 text-sm font-black text-indigo-700" @disabled(!$event->accommodation_published)>Email rooms to attendees</button></form>
                <form method="POST" action="{{ route('events.accommodation.invite-self-select', $event) }}">@csrf<button class="rounded-xl border border-indigo-200 px-4 py-2 text-sm font-black text-indigo-700">Send selection link</button></form>
                <a href="{{ route('events.accommodation.index', [$event, 'preview' => 1]) }}" class="rounded-xl border border-indigo-200 px-4 py-2 text-sm font-black text-indigo-700">Preview</a>
                <form method="POST" action="{{ route('events.accommodation.allocate', $event) }}">@csrf<button class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-black text-white" @disabled(!$event->accommodation_enabled)>Assign rooms now</button></form>
            </div></div>
            <p class="mt-3 text-xs font-bold {{ $event->accommodationSelfSelectOpen() ? 'text-emerald-600' : 'text-slate-400' }}">
                @if($event->accommodationSelfSelectOpen())
                    Attendees can pick their own room until {{ $event->accommodation_self_select_closes_at->format('D j M Y, g:ia') }} — "Send selection link" emails them the link.
                @else
                    "Send selection link" is inactive: tick <strong>Use accommodation</strong> and set a future <strong>"Let attendees pick until"</strong> time in the header, then <strong>Save</strong>.
                @endif
            </p>
            @if($preview)
                <div class="mt-5 grid gap-4 lg:grid-cols-2"><div class="rounded-2xl bg-emerald-50 p-4"><h3 class="font-black text-emerald-900">{{ $preview['proposals']->count() }} proposed</h3>@foreach($preview['proposals'] as $proposal)<p class="mt-2 text-sm text-emerald-800">{{ $proposal['registration']->participant->name }} → {{ $proposal['room']->label() }}</p>@endforeach</div><div class="rounded-2xl bg-amber-50 p-4"><h3 class="font-black text-amber-900">{{ $preview['unallocated']->count() }} unallocated</h3>@foreach($preview['unallocated'] as $item)<p class="mt-2 text-sm text-amber-800">{{ $item['registration']->participant->name }} — {{ $item['reason'] }}</p>@endforeach</div></div>
            @endif
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-black text-slate-900">Buildings &amp; rooms</h2>
                <div class="flex flex-wrap gap-2 text-sm">
                    <details data-acc="addsite">
                        <summary class="cursor-pointer list-none rounded-xl bg-slate-900 px-4 py-2 font-black text-white">+ Add location</summary>
                        <form method="POST" action="{{ route('events.accommodation.sites.store', $event) }}" class="mt-2 grid gap-3 rounded-2xl bg-slate-50 p-4 md:grid-cols-4">@csrf
                            <input name="name" required placeholder="Location name (campus, hotel…)" class="rounded-xl border-slate-300 text-sm">
                            <input name="address" placeholder="Address" class="rounded-xl border-slate-300 text-sm">
                            <input name="check_in_instructions" placeholder="Check-in instructions" class="rounded-xl border-slate-300 text-sm">
                            <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-black text-white">Add location</button>
                        </form>
                    </details>
                    <details data-acc="importrooms">
                        <summary class="cursor-pointer list-none rounded-xl border border-indigo-200 px-4 py-2 font-black text-indigo-700">Import rooms (CSV)</summary>
                        <form method="POST" enctype="multipart/form-data" action="{{ route('events.accommodation.import', $event) }}" class="mt-2 flex flex-wrap items-center gap-3 rounded-2xl border border-dashed border-indigo-200 bg-indigo-50 p-4">@csrf
                            <input type="file" name="file" accept=".csv,text/csv" required class="text-sm">
                            <button class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-black text-white">Import CSV</button>
                            <span class="text-xs text-indigo-700">Columns: location, building, floor, room, capacity; optional gender, category, accessible (yes/no for step-free). "site"/"block" also accepted.</span>
                        </form>
                    </details>
                </div>
            </div>

            @php $autoOpenSites = $errors->any() || $event->accommodationSites->count() <= 1; @endphp
            <div class="mt-5 space-y-3">
                @forelse($event->accommodationSites as $site)
                    @php
                        $siteFloors = $site->blocks->flatMap->floors;
                        $siteRooms = $siteFloors->flatMap->rooms;
                    @endphp
                    <details data-acc="site-{{ $site->id }}" @if($autoOpenSites) open @endif class="group rounded-2xl border border-slate-200">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-2xl p-4 hover:bg-slate-50">
                            <span class="min-w-0">
                                <span class="block truncate text-base font-black text-slate-900">{{ $site->name }}@unless($site->is_active)<span class="ml-2 rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-black uppercase text-slate-600">Inactive</span>@endunless</span>
                                <span class="text-xs text-slate-500">{{ $site->blocks->count() }} building(s) · {{ $siteFloors->count() }} floor(s) · {{ $siteRooms->count() }} room(s) · {{ $siteRooms->sum('capacity') }} bed(s)</span>
                            </span>
                            <svg class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </summary>
                        <div class="space-y-3 border-t border-slate-100 p-4">
                            <div class="flex flex-wrap gap-2">
                                <details data-acc="addblock-{{ $site->id }}">
                                    <summary class="cursor-pointer list-none rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-black text-white">+ Add building</summary>
                                    <form method="POST" action="{{ route('events.accommodation.blocks.store', [$event, $site]) }}" class="mt-2 grid gap-2 md:grid-cols-5">@csrf
                                        <input name="name" required placeholder="Building name (e.g. Block A)" class="rounded-xl border-slate-300 text-sm">
                                        <select name="gender_restriction" class="rounded-xl border-slate-300 text-sm"><option value="">Open to anyone</option><option>Male</option><option>Female</option></select>
                                        <input name="category_restriction" placeholder="Category (optional)" class="rounded-xl border-slate-300 text-sm">
                                        <select name="priority" class="rounded-xl border-slate-300 text-sm">@foreach(Priority::options(null) as $label => $value)<option value="{{ $value }}" @selected($value === Priority::DEFAULT)>{{ $label }}</option>@endforeach</select>
                                        <button class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-black text-white">Add building</button>
                                    </form>
                                </details>
                                <details>
                                    <summary class="cursor-pointer list-none rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-black text-indigo-700">Edit / delete location</summary>
                                    <div class="mt-2 flex flex-wrap items-start gap-3">
                                        <form method="POST" action="{{ route('events.accommodation.sites.update', [$event, $site]) }}" class="grid gap-2">@csrf @method('PATCH')
                                            <input name="name" value="{{ $site->name }}" required class="rounded-lg border-slate-300 text-xs">
                                            <input name="address" value="{{ $site->address }}" placeholder="Address" class="rounded-lg border-slate-300 text-xs">
                                            <input name="check_in_instructions" value="{{ $site->check_in_instructions }}" placeholder="Instructions" class="rounded-lg border-slate-300 text-xs">
                                            <label class="text-xs"><input type="checkbox" name="is_active" value="1" @checked($site->is_active)> Active</label>
                                            <button class="rounded-lg bg-indigo-600 px-3 py-1 text-xs font-black text-white">Save</button>
                                        </form>
                                        <form method="POST" action="{{ route('events.accommodation.inventory.destroy', [$event, 'site', $site->id]) }}">@csrf @method('DELETE')<button class="text-xs font-bold text-red-600">Delete empty location</button></form>
                                    </div>
                                </details>
                            </div>

                            @forelse($site->blocks as $block)
                                @php $blockRooms = $block->floors->flatMap->rooms; @endphp
                                <details data-acc="block-{{ $block->id }}" class="group rounded-xl border border-slate-200 bg-slate-50">
                                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-xl p-3 hover:bg-slate-100">
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-black text-slate-800">{{ $block->name }}@unless($block->is_active)<span class="ml-2 rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-black uppercase text-slate-600">Inactive</span>@endunless</span>
                                            <span class="text-xs text-slate-500">{{ $block->gender_restriction ?: 'Open to anyone' }}{{ $block->category_restriction ? ' · '.$block->category_restriction : '' }} · {{ $block->floors->count() }} floor(s) · {{ $blockRooms->count() }} room(s)</span>
                                        </span>
                                        <svg class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                    </summary>
                                    <div class="space-y-3 border-t border-slate-200 p-3">
                                        <div class="flex flex-wrap gap-2">
                                            <details data-acc="addfloor-{{ $block->id }}">
                                                <summary class="cursor-pointer list-none rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-black text-white">+ Add floor</summary>
                                                <form method="POST" action="{{ route('events.accommodation.floors.store', [$event, $block]) }}" class="mt-2 flex flex-wrap gap-2">@csrf
                                                    <input name="name" required placeholder="Floor name" class="rounded-xl border-slate-300 text-sm">
                                                    <select name="priority" class="w-40 rounded-xl border-slate-300 text-sm">@foreach(Priority::options(null) as $label => $value)<option value="{{ $value }}" @selected($value === Priority::DEFAULT)>{{ $label }}</option>@endforeach</select>
                                                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_accessible" value="1"> Step-free access (no stairs)</label>
                                                    <button class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-black text-white">Add floor</button>
                                                </form>
                                            </details>
                                            <details>
                                                <summary class="cursor-pointer list-none rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-black text-indigo-700">Edit / delete building</summary>
                                                <div class="mt-2 flex flex-wrap items-start gap-3">
                                                    <form method="POST" action="{{ route('events.accommodation.blocks.update', [$event, $block]) }}" class="grid gap-2">@csrf @method('PATCH')
                                                        <input name="name" value="{{ $block->name }}" required class="rounded-lg border-slate-300 text-xs">
                                                        <select name="gender_restriction" class="rounded-lg border-slate-300 text-xs"><option value="">Open to anyone</option><option @selected($block->gender_restriction === 'Male')>Male</option><option @selected($block->gender_restriction === 'Female')>Female</option></select>
                                                        <input name="category_restriction" value="{{ $block->category_restriction }}" placeholder="Category" class="rounded-lg border-slate-300 text-xs">
                                                        <select name="priority" class="rounded-lg border-slate-300 text-xs">@foreach(Priority::options($block->priority) as $label => $value)<option value="{{ $value }}" @selected($value === (int) $block->priority)>{{ $label }}</option>@endforeach</select>
                                                        <label class="text-xs"><input type="checkbox" name="is_active" value="1" @checked($block->is_active)> Active</label>
                                                        <button class="rounded-lg bg-indigo-600 px-3 py-1 text-xs font-black text-white">Save</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('events.accommodation.inventory.destroy', [$event, 'block', $block->id]) }}">@csrf @method('DELETE')<button class="text-xs font-bold text-red-600">Delete empty building</button></form>
                                                </div>
                                            </details>
                                        </div>

                                        @forelse($block->floors as $floor)
                                            <details data-acc="floor-{{ $floor->id }}" class="group rounded-xl border border-slate-200 bg-white">
                                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-xl p-3 hover:bg-slate-50">
                                                    <span class="min-w-0">
                                                        <span class="block truncate text-sm font-bold text-slate-700">{{ $floor->name }} @if($floor->is_accessible)<span class="rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-black uppercase text-indigo-700">Step-free</span>@endif@unless($floor->is_active)<span class="ml-1 rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-black uppercase text-slate-600">Inactive</span>@endunless</span>
                                                        <span class="text-xs text-slate-500">{{ $floor->rooms->count() }} room(s) · {{ $floor->rooms->sum('active_assignments_count') }}/{{ $floor->rooms->sum('capacity') }} bed(s) filled</span>
                                                    </span>
                                                    <svg class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                                </summary>
                                                <div class="space-y-3 border-t border-slate-100 p-3">
                                                    <div class="flex flex-wrap gap-2">
                                                        <details data-acc="addroom-{{ $floor->id }}">
                                                            <summary class="cursor-pointer list-none rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-black text-white">+ Add room</summary>
                                                            <form method="POST" action="{{ route('events.accommodation.rooms.store', [$event, $floor]) }}" class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">@csrf
                                                                <input name="name" required placeholder="Room name / number" class="rounded-xl border-slate-300 text-sm">
                                                                <input name="capacity" required type="number" min="1" placeholder="Beds" class="rounded-xl border-slate-300 text-sm">
                                                                <select name="gender_restriction" class="rounded-xl border-slate-300 text-sm"><option value="">Same as building</option><option>Male</option><option>Female</option></select>
                                                                <input name="category_restriction" placeholder="Category" class="rounded-xl border-slate-300 text-sm">
                                                                <select name="priority" class="rounded-xl border-slate-300 text-sm">@foreach(Priority::options(null) as $label => $value)<option value="{{ $value }}" @selected($value === Priority::DEFAULT)>{{ $label }}</option>@endforeach</select>
                                                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="is_accessible" value="1"> Step-free access (no stairs)</label>
                                                                <button class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-black text-white sm:col-span-2 lg:col-span-3">Add room</button>
                                                            </form>
                                                        </details>
                                                        <details data-acc="bulkroom-{{ $floor->id }}">
                                                            <summary class="cursor-pointer list-none rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-black text-indigo-700">Add many rooms at once</summary>
                                                            <form method="POST" action="{{ route('events.accommodation.rooms.bulk', [$event, $floor]) }}" class="mt-2 flex flex-wrap gap-2 rounded-xl bg-indigo-50 p-3">@csrf
                                                                <input name="prefix" required placeholder="Prefix (A)" class="w-28 rounded-lg border-indigo-200 text-xs">
                                                                <input name="start" type="number" min="0" required placeholder="From" class="w-20 rounded-lg border-indigo-200 text-xs">
                                                                <input name="end" type="number" min="0" required placeholder="To" class="w-20 rounded-lg border-indigo-200 text-xs">
                                                                <input name="capacity" type="number" min="1" required placeholder="Beds" class="w-20 rounded-lg border-indigo-200 text-xs">
                                                                <select name="gender_restriction" class="rounded-lg border-indigo-200 text-xs"><option value="">Same as building</option><option>Male</option><option>Female</option></select>
                                                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="is_accessible" value="1"> Step-free access (no stairs)</label>
                                                                <button class="rounded-lg bg-indigo-700 px-3 py-2 text-xs font-black text-white">Create range</button>
                                                            </form>
                                                        </details>
                                                        <details>
                                                            <summary class="cursor-pointer list-none rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-black text-indigo-700">Edit / delete floor</summary>
                                                            <div class="mt-2 flex flex-wrap items-start gap-3">
                                                                <form method="POST" action="{{ route('events.accommodation.floors.update', [$event, $floor]) }}" class="grid gap-2">@csrf @method('PATCH')
                                                                    <input name="name" value="{{ $floor->name }}" required class="rounded-lg border-slate-300 text-xs">
                                                                    <select name="priority" class="rounded-lg border-slate-300 text-xs">@foreach(Priority::options($floor->priority) as $label => $value)<option value="{{ $value }}" @selected($value === (int) $floor->priority)>{{ $label }}</option>@endforeach</select>
                                                                    <label class="text-xs"><input type="checkbox" name="is_accessible" value="1" @checked($floor->is_accessible)> Step-free access (no stairs)</label>
                                                                    <label class="text-xs"><input type="checkbox" name="is_active" value="1" @checked($floor->is_active)> Active</label>
                                                                    <button class="rounded-lg bg-indigo-600 px-3 py-1 text-xs font-black text-white">Save</button>
                                                                </form>
                                                                <form method="POST" action="{{ route('events.accommodation.inventory.destroy', [$event, 'floor', $floor->id]) }}">@csrf @method('DELETE')<button class="text-xs font-bold text-red-600">Delete empty floor</button></form>
                                                            </div>
                                                        </details>
                                                    </div>

                                                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                                        @forelse($floor->rooms as $room)
                                                            <div class="rounded-xl border border-slate-200 bg-white p-3">
                                                                <form method="POST" action="{{ route('events.accommodation.rooms.update', [$event, $room]) }}">@csrf @method('PATCH')
                                                                    <div class="flex items-center justify-between gap-2">
                                                                        <input name="name" value="{{ $room->name }}" required class="w-28 rounded border-slate-300 p-1 text-xs font-bold">
                                                                        <span class="text-xs font-bold">{{ $room->active_assignments_count }}/<input name="capacity" type="number" min="{{ $room->active_assignments_count }}" value="{{ $room->capacity }}" class="w-16 rounded border-slate-300 p-1 text-xs"></span>
                                                                    </div>
                                                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                                                        <select name="status" class="rounded border-slate-300 p-1 text-xs">@foreach(['active' => 'active', 'reserved' => 'reserved (manual only)', 'closed' => 'closed (out of service)'] as $value => $label)<option value="{{ $value }}" @selected($room->status === $value)>{{ $label }}</option>@endforeach</select>
                                                                        <select name="gender_restriction" class="rounded border-slate-300 p-1 text-xs"><option value="">Same as building</option><option @selected($room->gender_restriction === 'Male')>Male</option><option @selected($room->gender_restriction === 'Female')>Female</option></select>
                                                                        <input name="category_restriction" value="{{ $room->category_restriction }}" placeholder="Category" class="rounded border-slate-300 p-1 text-xs">
                                                                        <select name="priority" class="rounded border-slate-300 p-1 text-xs">@foreach(Priority::options($room->priority) as $label => $value)<option value="{{ $value }}" @selected($value === (int) $room->priority)>{{ $label }}</option>@endforeach</select>
                                                                    </div>
                                                                    <label class="mt-2 flex items-center gap-1 text-xs"><input type="checkbox" name="is_accessible" value="1" @checked($room->is_accessible)> Step-free access (no stairs)</label>
                                                                    <button class="mt-2 rounded-lg bg-indigo-600 px-3 py-1 text-xs font-black text-white">Save room</button>
                                                                </form>
                                                                <form method="POST" action="{{ route('events.accommodation.inventory.destroy', [$event, 'room', $room->id]) }}">@csrf @method('DELETE')<button class="mt-2 text-xs font-bold text-red-600">Delete empty room</button></form>
                                                            </div>
                                                        @empty
                                                            <p class="text-xs text-slate-400">No rooms yet — use "+ Add room".</p>
                                                        @endforelse
                                                    </div>
                                                </div>
                                            </details>
                                        @empty
                                            <p class="text-xs text-slate-400">No floors yet — use "+ Add floor".</p>
                                        @endforelse
                                    </div>
                                </details>
                            @empty
                                <p class="text-xs text-slate-400">No buildings yet — use "+ Add building".</p>
                            @endforelse
                        </div>
                    </details>
                @empty
                    <p class="py-8 text-center text-sm text-slate-500">Add a location to begin, then add buildings, floors and rooms inside it.</p>
                @endforelse
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <details data-acc="attendees" open class="group">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 border-b border-slate-100 p-6">
                    <span>
                        <span class="block text-xl font-black">Who needs a room</span>
                        <span class="mt-1 block text-sm text-slate-500">Only you can see this until you tick "Show rooms to attendees".</span>
                    </span>
                    <svg class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div x-data="{ q: '', unassignedOnly: false, needsOnly: false }" class="p-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <input x-model="q" type="search" placeholder="Search name, email or room…" class="w-full rounded-xl border-slate-300 text-sm sm:w-72">
                        <label class="flex items-center gap-2 text-xs font-bold text-slate-600"><input type="checkbox" x-model="unassignedOnly"> Unassigned only</label>
                        <label class="flex items-center gap-2 text-xs font-bold text-slate-600"><input type="checkbox" x-model="needsOnly"> Needs room only</label>
                        <details class="ml-auto">
                            <summary class="cursor-pointer list-none rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-black text-indigo-700">Everyone needs a room</summary>
                            <form method="POST" action="{{ route('events.accommodation.mark-all-required', $event) }}" class="mt-2 flex flex-wrap items-center gap-2 rounded-xl bg-indigo-50 p-3">@csrf
                                <span class="text-xs text-slate-600">Ticks "Needs room" for every confirmed attendee. Type <strong>{{ $event->title }}</strong> to confirm:</span>
                                <input name="confirm_title" autocomplete="off" placeholder="{{ $event->title }}" class="rounded-lg border-indigo-200 text-xs">
                                <button class="rounded-lg bg-indigo-600 px-3 py-1 text-xs font-black text-white">Apply</button>
                            </form>
                        </details>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">Rooms set to <strong>reserved</strong> are skipped by "Assign rooms now" — pick them here to hand-place special guests, then tick <strong>Lock</strong>. "Closed" rooms can't be assigned at all.</p>
                    <div class="mt-4 overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Attendee</th><th class="px-5 py-3">Room needs</th><th class="px-5 py-3">Assignment</th><th class="px-5 py-3">Assign a room</th></tr></thead><tbody class="divide-y divide-slate-100">
                        @forelse($registrations as $registration)
                            @php
                                $assigned = (bool) $registration->roomAssignment;
                                $haystack = strtolower(trim($registration->participant->name.' '.$registration->participant->email.' '.($registration->roomAssignment?->room->label() ?? '')));
                            @endphp
                            <tr data-hay="{{ $haystack }}" data-assigned="{{ $assigned ? 1 : 0 }}" data-needs="{{ $registration->accommodation_required ? 1 : 0 }}" x-show="(q.trim() === '' || $el.dataset.hay.includes(q.trim().toLowerCase())) && (!unassignedOnly || $el.dataset.assigned === '0') && (!needsOnly || $el.dataset.needs === '1')"><td class="px-5 py-4"><strong>{{ $registration->participant->name }}</strong><div class="text-xs text-slate-500">{{ $registration->participant->gender ?: 'Gender unspecified' }} · {{ $registration->participant->category ?: 'No category' }}</div><a href="{{ route('events.accommodation.room-preview', [$event, $registration]) }}" target="_blank" class="mt-1 inline-block text-xs font-bold text-indigo-600 underline">Preview picker page</a></td><td class="px-5 py-4"><form method="POST" action="{{ route('events.accommodation.requirements.update', [$event, $registration]) }}" class="space-y-2">@csrf @method('PATCH')<label class="flex gap-2"><input type="checkbox" name="accommodation_required" value="1" @checked($registration->accommodation_required)> Needs room</label><label class="flex gap-2"><input type="checkbox" name="accessibility_required" value="1" @checked($registration->accessibility_required)> Needs a step-free room</label><input name="accommodation_notes" value="{{ $registration->accommodation_notes }}" placeholder="Notes" class="w-48 rounded-lg border-slate-300 p-1 text-xs"><button class="mt-1 block rounded-lg bg-indigo-600 px-3 py-1 text-xs font-black text-white">Save requirement</button></form></td><td class="px-5 py-4">@if($registration->roomAssignment)<strong>{{ $registration->roomAssignment->room->label() }}</strong><div class="text-xs font-bold capitalize text-slate-500">{{ str_replace('_', ' ', $registration->roomAssignment->status) }} · {{ ucfirst($registration->roomAssignment->method) }}{{ $registration->roomAssignment->is_locked ? ' · Locked' : '' }}</div><div class="mt-2 flex gap-2">@if($registration->roomAssignment->status === 'assigned')<form method="POST" action="{{ route('events.accommodation.check-in', [$event, $registration]) }}">@csrf<button class="rounded-lg bg-emerald-600 px-2 py-1 text-xs font-black text-white">Check in</button></form>@elseif($registration->roomAssignment->status === 'checked_in')<form method="POST" action="{{ route('events.accommodation.check-out', [$event, $registration]) }}">@csrf<button class="rounded-lg bg-slate-700 px-2 py-1 text-xs font-black text-white">Check out</button></form>@endif</div>@else<span class="font-bold text-amber-700">No room yet</span>@endif</td><td class="px-5 py-4"><form method="POST" action="{{ route('events.accommodation.assignments.update', [$event, $registration]) }}" class="flex flex-wrap gap-2">@csrf @method('PUT')<select name="room_id" required class="max-w-52 rounded-lg border-slate-300 text-xs"><option value="">Choose room</option>@foreach($rooms->whereIn('status', ['active', 'reserved'])->sortBy('status') as $room)<option value="{{ $room->id }}" @selected($registration->roomAssignment?->accommodation_room_id === $room->id)>{{ $room->label() }} ({{ $room->active_assignments_count }}/{{ $room->capacity }}){{ $room->status === 'reserved' ? ' — reserved' : '' }}</option>@endforeach</select><label class="flex items-center gap-1 text-xs"><input type="checkbox" name="is_locked" value="1" @checked($registration->roomAssignment?->is_locked)> Lock</label><button class="rounded-lg bg-indigo-600 px-3 py-1 text-xs font-black text-white">Assign</button></form>@if($registration->roomAssignment && !in_array($registration->roomAssignment->status, ['checked_in'], true))<form method="POST" action="{{ route('events.accommodation.assignments.destroy', [$event, $registration]) }}" class="mt-2">@csrf @method('DELETE')<button class="text-xs font-bold text-red-600">Remove assignment</button></form>@endif</td></tr>
                        @empty<tr><td colspan="4" class="p-8 text-center text-slate-500">No confirmed attendees yet.</td></tr>@endforelse
                    </tbody></table></div>
                </div>
            </details>
        </section>
    </div></div>

    {{-- Keep expanded panels and scroll position across the form round-trips on this page. --}}
    <script>
        (() => {
            const OPEN = 'acc-open:{{ $event->id }}';
            const SCROLL = 'acc-scroll:{{ $event->id }}';
            const read = () => { try { return new Set(JSON.parse(localStorage.getItem(OPEN) || '[]')); } catch (e) { return new Set(); } };
            const write = (s) => { try { localStorage.setItem(OPEN, JSON.stringify([...s])); } catch (e) {} };

            const remembered = read();
            document.querySelectorAll('details[data-acc]').forEach((d) => {
                if (remembered.has(d.dataset.acc)) d.open = true;
                d.addEventListener('toggle', () => {
                    const s = read();
                    if (d.open) s.add(d.dataset.acc); else s.delete(d.dataset.acc);
                    write(s);
                });
            });

            try {
                if (history.scrollRestoration) history.scrollRestoration = 'manual';
                const y = sessionStorage.getItem(SCROLL);
                if (y !== null) { window.scrollTo(0, parseInt(y, 10) || 0); sessionStorage.removeItem(SCROLL); }
            } catch (e) {}
            document.querySelectorAll('form').forEach((f) => f.addEventListener('submit', () => {
                try { sessionStorage.setItem(SCROLL, String(window.scrollY)); } catch (e) {}
            }));
        })();
    </script>
</x-app-layout>
