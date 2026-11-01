<x-app-layout>
    <x-slot name="header">Food Distribution</x-slot>

    <x-event-closed-banner :event="$event" />

    @php
        $openCount = $meals->filter->isOpen()->count();
        $portions = $meals->sum('total_portions');
        $icons = [
            'bowl' => 'M3 11h18a9 9 0 01-18 0zM12 3v4M8 5v2M16 5v2',
            'pin' => 'M12 21s-7-6.5-7-12a7 7 0 1114 0c0 5.5-7 12-7 12zM12 11a2 2 0 100-4 2 2 0 000 4z',
            'staff' => 'M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87m4-5.13a4 4 0 11-8 0 4 4 0 018 0zm6 2a3 3 0 11-6 0 3 3 0 016 0z',
            'ticket' => 'M15 5v2m0 4v2m0 4v2M5 5h14a2 2 0 012 2v3a2 2 0 000 4v3a2 2 0 01-2 2H5a2 2 0 01-2-2v-3a2 2 0 000-4V7a2 2 0 012-2z',
            'report' => 'M9 17v-6m4 6V7m4 10v-3M5 21h14',
            'scan' => 'M4 7V5a1 1 0 011-1h2M17 4h2a1 1 0 011 1v2M20 17v2a1 1 0 01-1 1h-2M7 20H5a1 1 0 01-1-1v-2M8 12h8',
            'clock' => 'M12 8v4l3 3M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            'edit' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.6a2 2 0 112.8 2.8L11.8 15.8 8 17l1.2-3.8 8.4-8.8z',
            'waste' => 'M19 7l-.87 12.14A2 2 0 0116.14 21H7.86a2 2 0 01-1.99-1.86L5 7m5 4v6m4-6v6M4 7h16M9 7V4h6v3',
        ];
        $tones = ['from-orange-400 to-rose-500', 'from-amber-400 to-orange-500', 'from-emerald-400 to-teal-600', 'from-sky-400 to-blue-600', 'from-fuchsia-500 to-purple-600', 'from-rose-400 to-pink-600'];
    @endphp

    <div class="space-y-7">
        <section class="rounded-3xl bg-gradient-to-br from-orange-500 via-rose-600 to-rose-900 p-8 text-white shadow-xl">
            <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
                <div class="flex items-start gap-5">
                    <span class="hidden h-14 w-14 shrink-0 place-items-center rounded-2xl bg-white/15 sm:grid">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['bowl'] }}"/></svg>
                    </span>
                    <div>
                        <p class="text-xs font-black uppercase tracking-[.2em] text-orange-100">Meals and refreshments</p>
                        <h1 class="mt-2 text-3xl font-black">{{ $event->title }}</h1>
                        <p class="mt-3 max-w-2xl text-sm text-orange-50">Create serving sessions and scan attendee or staff badge QR codes to issue food once. {{ $confirmedCount }} confirmed attendee(s) and staff member(s) for this event.</p>
                    </div>
                </div>
                @can('manageMeals', $event)
                    <div data-tour="event-food-actions" class="flex flex-wrap gap-2 rounded-2xl bg-white/10 p-3">
                        <a href="{{ route('events.meals.vouchers', $event) }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 text-sm font-black text-white hover:bg-white/25">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['ticket'] }}"/></svg>Print vouchers</a>
                        <a href="{{ route('events.meals.report', $event) }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-black text-rose-700 hover:bg-orange-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['report'] }}"/></svg>View food report</a>
                    </div>
                @endcan
            </div>
        </section>

        <livewire:meal-overview-stats :event="$event" />

        @if($errors->any())<p class="rounded-2xl bg-red-50 p-4 text-sm font-bold text-red-700">{{ $errors->first() }}</p>@endif

        @can('manageMeals', $event)
            {{-- Setup: each card opens its panel below the row. --}}
            <div x-data="{ panel: @js($errors->any() ? 'create' : null) }" class="space-y-4">
                @php
                    $setup = [
                        ['create', 'New food distribution', 'A lunch, dinner or snack pack, with how many portions there are.', $meals->count().' created · '.$openCount.' open now', $icons['bowl'], 'from-orange-400 to-rose-500', 'event-food-create'],
                        ['stations', 'Sharing points', 'The places food is handed out, like Gate A or the VIP tent.', $stations->count().' sharing point'.($stations->count() === 1 ? '' : 's'), $icons['pin'], 'from-emerald-400 to-teal-600', null],
                        ['staff', 'Assign staff', 'Choose which ushers and audit staff serve at each sharing point.', $stationStaff->count().' usher'.($stationStaff->count() === 1 ? '' : 's').' and audit staff available', $icons['staff'], 'from-sky-400 to-blue-600', null],
                    ];
                @endphp
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($setup as [$key, $title, $text, $stat, $icon, $tone, $tour])
                        <button type="button" @click="panel = panel === '{{ $key }}' ? null : '{{ $key }}'" @if($tour) data-tour="{{ $tour }}" @endif
                                :class="panel === '{{ $key }}' ? 'border-rose-300 ring-4 ring-rose-100' : 'border-slate-200 hover:border-rose-200'"
                                class="group flex flex-col overflow-hidden rounded-3xl border bg-white p-6 text-left shadow-sm transition hover:-translate-y-1 hover:shadow-xl">
                            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-gradient-to-br {{ $tone }} text-white shadow-lg">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                            </span>
                            <span class="mt-5 text-lg font-black text-slate-900">{{ $title }}</span>
                            <span class="mt-1 text-sm text-slate-500">{{ $text }}</span>
                            <span class="mt-5 flex w-full items-center justify-between border-t border-slate-100 pt-4 text-xs font-bold text-slate-500">{{ $stat }}
                                <span class="text-rose-600" x-text="panel === '{{ $key }}' ? 'Close ✕' : 'Open →'">Open →</span></span>
                        </button>
                    @endforeach
                </div>

                <div x-show="panel === 'create'" x-cloak data-no-lift class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <h3 class="border-b border-slate-100 px-6 py-4 text-sm font-black text-slate-900">New food distribution</h3>
                    <form method="POST" action="{{ route('events.meals.store', $event) }}" class="grid gap-5 p-6 sm:grid-cols-2">
                        @csrf
                        <div><label class="text-xs font-black uppercase text-slate-500">Name</label><input name="name" value="{{ old('name') }}" placeholder="Day 1 Lunch" required class="mt-2 w-full rounded-xl border-slate-200">@error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                        <div><label class="text-xs font-black uppercase text-slate-500">Available portions</label><input name="total_portions" type="number" min="1" value="{{ old('total_portions') }}" required class="mt-2 w-full rounded-xl border-slate-200">@error('total_portions')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                        <div><label class="text-xs font-black uppercase text-slate-500">Opens at (optional)</label><input name="opens_at" type="datetime-local" value="{{ old('opens_at') }}" class="mt-2 w-full rounded-xl border-slate-200"></div>
                        <div><label class="text-xs font-black uppercase text-slate-500">Closes at (optional)</label><input name="closes_at" type="datetime-local" value="{{ old('closes_at') }}" class="mt-2 w-full rounded-xl border-slate-200"></div>
                        <div><label class="text-xs font-black uppercase text-slate-500">Low stock alert threshold (optional)</label><input name="low_stock_threshold" type="number" min="0" value="{{ old('low_stock_threshold') }}" placeholder="e.g. 20" class="mt-2 w-full rounded-xl border-slate-200"><p class="mt-1 text-xs text-slate-400">Managers and assigned Audit Heads are notified once remaining stock drops to or below this number.</p></div>
                        <div><label class="text-xs font-black uppercase text-slate-500">Portion entitlements (optional)</label><textarea name="entitlements" rows="3" placeholder="VIP:2&#10;Staff:1" class="mt-2 w-full rounded-xl border-slate-200 font-mono text-xs"></textarea><p class="mt-1 text-xs text-slate-400">One "Category:portions" per line. Categories with no line here get 1 portion by default.</p></div>
                        <input type="hidden" name="is_active" value="1">
                        <div class="flex justify-end sm:col-span-2"><button class="rounded-xl bg-gradient-to-r from-orange-500 to-rose-600 px-6 py-3 text-sm font-extrabold text-white shadow-lg shadow-rose-200">Create distribution</button></div>
                    </form>
                </div>

                <div x-show="panel === 'stations'" x-cloak data-no-lift class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <h3 class="border-b border-slate-100 px-6 py-4 text-sm font-black text-slate-900">Sharing points ({{ $stations->count() }})</h3>
                    <form method="POST" action="{{ route('events.meals.stations.update', $event) }}" class="p-6">
                        @csrf
                        <label class="text-xs font-black uppercase text-slate-500">Sharing point names, one per line</label>
                        <textarea name="stations" rows="3" placeholder="Gate A&#10;Gate B&#10;VIP Tent" class="mt-2 w-full rounded-xl border-slate-200 font-mono text-xs">{{ old('stations', $stations->pluck('name')->implode("\n")) }}</textarea>
                        <p class="mt-1 text-xs text-slate-400">Existing sharing points with staff, allocated stock or collection history must be retained. Assign staff and allocate portions below.</p>
                        <button class="mt-4 rounded-xl bg-slate-900 px-5 py-3 text-xs font-black uppercase tracking-wider text-white">Save sharing points</button>
                    </form>
                </div>

                <div x-show="panel === 'staff'" x-cloak data-no-lift class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-6 py-4">
                        <h3 class="text-sm font-black text-slate-900">Assign ushers and audit staff to sharing points</h3>
                        <p class="mt-1 text-xs text-slate-500">Someone on a sharing point only sees that point in the food scanner and serves from it. Ushers on no point can serve anywhere.</p>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @forelse($stations as $station)
                            <form method="POST" action="{{ route('events.meals.stations.staff', [$event, $station]) }}" class="p-6">
                                @csrf
                                <h4 class="flex items-center gap-2 font-black text-slate-800">
                                    <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['pin'] }}"/></svg>{{ $station->name }}</h4>
                                <div class="my-3 flex flex-wrap gap-2">
                                    @forelse($stationStaff as $member)
                                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-slate-200 px-3 py-1.5 text-sm font-bold text-slate-700 has-[:checked]:border-sky-300 has-[:checked]:bg-sky-50 has-[:checked]:text-sky-800"><input type="checkbox" name="staff_ids[]" value="{{ $member->id }}" class="rounded text-sky-600" @checked($station->staff->contains('id', $member->id))> {{ $member->name }}
                                            <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-black uppercase text-slate-500">{{ $member->hasRole('usher') ? 'Usher' : 'Audit staff' }}</span></label>
                                    @empty<p class="text-sm text-slate-500">Assign ushers or audit staff to this event on the Team page first.</p>@endforelse
                                </div>
                                <button class="rounded-xl bg-sky-600 px-4 py-2 text-xs font-black text-white">Save staff assignment</button>
                            </form>
                        @empty<p class="p-6 text-sm text-slate-500">Create sharing points first.</p>@endforelse
                    </div>
                </div>
            </div>
        @endcan

        <div>
            <div class="mb-4 flex items-end justify-between">
                <h2 class="text-xl font-black text-slate-900">Food distributions</h2>
                @if($meals->isNotEmpty())<p class="text-xs font-bold text-slate-500">{{ $meals->count() }} distribution{{ $meals->count() === 1 ? '' : 's' }} · {{ number_format($portions) }} portions</p>@endif
            </div>
            <div class="grid items-start gap-5 lg:grid-cols-2">
                @forelse($meals as $meal)
                    @php
                        $tone = $tones[$loop->index % count($tones)];
                        [$status, $statusClass] = match (true) {
                            ! $meal->is_active => ['Paused', 'bg-slate-100 text-slate-600'],
                            $meal->isOpen() => ['Open now', 'bg-emerald-50 text-emerald-700'],
                            default => ['Closed', 'bg-slate-100 text-slate-500'],
                        };
                    @endphp
                    <article x-data="{ tool: null }" class="flex flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm" data-no-lift>
                        <div class="h-1.5 bg-gradient-to-r {{ $tone }}"></div>
                        <div class="p-6">
                            <div class="flex items-start gap-4">
                                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-gradient-to-br {{ $tone }} text-white shadow-lg">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['bowl'] }}"/></svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-xl font-black text-slate-900">{{ $meal->name }}</h3>
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider {{ $statusClass }}">
                                            @if($status === 'Open now')<span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>@endif{{ $status }}</span>
                                    </div>
                                    <p class="mt-1 flex items-center gap-1.5 text-xs text-slate-500">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['clock'] }}"/></svg>
                                        {{ $meal->opens_at?->format('M j, g:i A') ?? 'No opening time' }} — {{ $meal->closes_at?->format('M j, g:i A') ?? 'No closing time' }}</p>
                                </div>
                            </div>

                            <livewire:meal-distribution-stats :event="$event" :meal="$meal" :key="'meal-stats-'.$meal->id" />

                            <div class="mt-5 flex flex-wrap gap-2">
                                <a href="{{ route('events.meals.scanner', [$event, $meal]) }}" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r {{ $tone }} px-5 py-3 text-sm font-extrabold text-white shadow-md hover:opacity-90">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['scan'] }}"/></svg>Open food scanner</a>
                                @can('manageMeals', $event)
                                    <form method="POST" action="{{ route('events.meals.update', [$event, $meal]) }}">@csrf @method('PATCH')<input type="hidden" name="name" value="{{ $meal->name }}"><input type="hidden" name="total_portions" value="{{ $meal->total_portions }}"><input type="hidden" name="opens_at" value="{{ $meal->opens_at?->format('Y-m-d H:i:s') }}"><input type="hidden" name="closes_at" value="{{ $meal->closes_at?->format('Y-m-d H:i:s') }}"><input type="hidden" name="low_stock_threshold" value="{{ $meal->low_stock_threshold }}"><input type="hidden" name="entitlements" value="{{ $meal->entitlements->map(fn($e) => $e->category.':'.$e->portions_allowed)->implode("\n") }}"><input type="hidden" name="is_active" value="{{ $meal->is_active ? 0 : 1 }}"><button class="rounded-xl border border-slate-200 px-4 py-3 text-xs font-extrabold text-slate-600 hover:bg-slate-50">{{ $meal->is_active ? 'Pause' : 'Activate' }}</button></form>
                                @endcan
                            </div>
                        </div>

                        @can('manageMeals', $event)
                            <div class="mt-auto border-t border-slate-100 bg-slate-50/60 px-6 py-3">
                                <div class="flex flex-wrap gap-2">
                                    @foreach(array_filter(['edit' => ['Edit', $icons['edit']], 'waste' => ['Log waste', $icons['waste']], 'stations' => $stations->isNotEmpty() ? ['Station portions', $icons['pin']] : null]) as $key => [$label, $icon])
                                        <button type="button" @click="tool = tool === '{{ $key }}' ? null : '{{ $key }}'"
                                                :class="tool === '{{ $key }}' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                                                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-black">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>{{ $label }}</button>
                                    @endforeach
                                </div>

                                <div x-show="tool === 'edit'" x-cloak class="pt-4">
                                    <p class="text-xs font-black text-slate-500">Edit name, stock, entitlements, or serving time</p>
                                    <form method="POST" action="{{ route('events.meals.update', [$event, $meal]) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                                        @csrf @method('PATCH')
                                        <input name="name" value="{{ $meal->name }}" required class="rounded-lg border-slate-200 text-sm">
                                        <input name="total_portions" type="number" min="1" value="{{ $meal->total_portions }}" required class="rounded-lg border-slate-200 text-sm">
                                        <input name="opens_at" type="datetime-local" value="{{ $meal->opens_at?->format('Y-m-d\TH:i') }}" class="rounded-lg border-slate-200 text-sm">
                                        <input name="closes_at" type="datetime-local" value="{{ $meal->closes_at?->format('Y-m-d\TH:i') }}" class="rounded-lg border-slate-200 text-sm">
                                        <input name="low_stock_threshold" type="number" min="0" value="{{ $meal->low_stock_threshold }}" placeholder="Low stock threshold" class="rounded-lg border-slate-200 text-sm sm:col-span-2">
                                        <textarea name="entitlements" rows="3" placeholder="VIP:2&#10;Staff:1" class="rounded-lg border-slate-200 font-mono text-xs sm:col-span-2">{{ $meal->entitlements->map(fn($e) => $e->category.':'.$e->portions_allowed)->implode("\n") }}</textarea>
                                        <input type="hidden" name="is_active" value="{{ $meal->is_active ? 1 : 0 }}">
                                        <button class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-black text-white sm:col-span-2">Save changes</button>
                                    </form>
                                    @if($meal->collections_count === 0)<form method="POST" action="{{ route('events.meals.destroy', [$event, $meal]) }}" class="mt-2">@csrf @method('DELETE')<button class="rounded-lg px-4 py-2 text-xs font-black text-red-600">Delete distribution</button></form>@endif
                                </div>

                                <div x-show="tool === 'waste'" x-cloak class="pt-4">
                                    <form method="POST" action="{{ route('events.meals.waste', [$event, $meal]) }}" class="grid gap-3 sm:grid-cols-[100px_1fr_auto]">
                                        @csrf
                                        <input name="quantity" type="number" min="1" placeholder="Qty" required class="rounded-lg border-slate-200 text-sm">
                                        <input name="reason" placeholder="Reason (spoiled, over-prepared, dropped...)" required class="rounded-lg border-slate-200 text-sm">
                                        <button class="rounded-lg bg-rose-600 px-4 py-2 text-xs font-black text-white">Log waste</button>
                                    </form>
                                </div>

                                @if($stations->isNotEmpty())
                                    <div x-show="tool === 'stations'" x-cloak class="pt-4">
                                        <form method="POST" action="{{ route('events.meals.stations.allocations.update', [$event, $meal]) }}" class="space-y-3">
                                            @csrf @method('PUT')
                                            @foreach($stations as $station)
                                                @php $allocated = $meal->stationAllocations->firstWhere('meal_station_id', $station->id)?->allocated_portions; @endphp
                                                <div class="flex items-center justify-between gap-3">
                                                    <label for="allocation-{{ $meal->id }}-{{ $station->id }}" class="text-sm font-bold text-slate-700">{{ $station->name }}</label>
                                                    <input id="allocation-{{ $meal->id }}-{{ $station->id }}" type="number" min="0" name="allocations[{{ $station->id }}]" value="{{ old('allocations.'.$station->id, $allocated) }}" placeholder="No cap" class="w-32 rounded-lg border-slate-200 text-sm">
                                                </div>
                                            @endforeach
                                            <p class="text-xs text-slate-400">Leave a station blank for no per-station cap — it can still serve from the shared {{ $meal->total_portions }}-portion stock. Set a number to stop the scanner from issuing more than that at this station.</p>
                                            <button class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-black text-white">Save station allocations</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        @endcan
                    </article>
                @empty
                    <div class="flex flex-col items-center rounded-3xl border-2 border-dashed border-slate-300 p-12 text-center lg:col-span-2">
                        <span class="grid h-14 w-14 place-items-center rounded-2xl bg-orange-50 text-orange-500">
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['bowl'] }}"/></svg>
                        </span>
                        <p class="mt-4 text-sm font-bold text-slate-700">No food distributions have been created for this event.</p>
                        @can('manageMeals', $event)<p class="mt-1 text-sm text-slate-500">Use <span class="font-black">New food distribution</span> above to add the first one.</p>@endcan
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
