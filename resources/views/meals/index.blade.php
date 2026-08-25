<x-app-layout>
    <x-slot name="header">Food Distribution</x-slot>

    <div class="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="text-xs font-extrabold uppercase tracking-wider text-blue-600">{{ $event->title }}</p><h2 class="mt-1 text-3xl font-black">Meals and refreshments</h2><p class="mt-2 text-sm text-slate-500">Create serving sessions and use attendee QR codes to issue food once. {{ $confirmedCount }} confirmed attendee(s) for this event.</p></div>@can('update', $event)<div class="flex gap-2"><a href="{{ route('events.meals.vouchers', $event) }}" target="_blank" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-center text-sm font-extrabold text-slate-700">Print vouchers</a><a href="{{ route('events.meals.report', $event) }}" class="rounded-xl border border-blue-200 bg-blue-50 px-5 py-3 text-center text-sm font-extrabold text-blue-800">View food report</a></div>@endcan</div>

    @can('update', $event)
        <details class="mb-7 rounded-2xl border border-slate-200 bg-white shadow-sm" @if($errors->any()) open @endif>
            <summary class="cursor-pointer px-6 py-5 text-sm font-black text-blue-700">+ Create food distribution</summary>
            <form method="POST" action="{{ route('events.meals.store', $event) }}" class="grid gap-5 border-t border-slate-100 p-6 sm:grid-cols-2">
                @csrf
                <div><label class="text-xs font-black uppercase text-slate-500">Name</label><input name="name" value="{{ old('name') }}" placeholder="Day 1 Lunch" required class="mt-2 w-full rounded-xl border-slate-200">@error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-xs font-black uppercase text-slate-500">Available portions</label><input name="total_portions" type="number" min="1" value="{{ old('total_portions') }}" required class="mt-2 w-full rounded-xl border-slate-200">@error('total_portions')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="text-xs font-black uppercase text-slate-500">Opens at (optional)</label><input name="opens_at" type="datetime-local" value="{{ old('opens_at') }}" class="mt-2 w-full rounded-xl border-slate-200"></div>
                <div><label class="text-xs font-black uppercase text-slate-500">Closes at (optional)</label><input name="closes_at" type="datetime-local" value="{{ old('closes_at') }}" class="mt-2 w-full rounded-xl border-slate-200"></div>
                <div><label class="text-xs font-black uppercase text-slate-500">Low stock alert threshold (optional)</label><input name="low_stock_threshold" type="number" min="0" value="{{ old('low_stock_threshold') }}" placeholder="e.g. 20" class="mt-2 w-full rounded-xl border-slate-200"><p class="mt-1 text-xs text-slate-400">Managers are notified once remaining stock drops to or below this number.</p></div>
                <div><label class="text-xs font-black uppercase text-slate-500">Portion entitlements (optional)</label><textarea name="entitlements" rows="3" placeholder="VIP:2&#10;Staff:1" class="mt-2 w-full rounded-xl border-slate-200 font-mono text-xs"></textarea><p class="mt-1 text-xs text-slate-400">One "Category:portions" per line. Categories with no line here get 1 portion by default.</p></div>
                <input type="hidden" name="is_active" value="1">
                <div class="flex justify-end sm:col-span-2"><button class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-extrabold text-white">Create distribution</button></div>
            </form>
        </details>

        <details class="mb-7 rounded-2xl border border-slate-200 bg-white shadow-sm">
            <summary class="cursor-pointer px-6 py-5 text-sm font-black text-blue-700">Serving stations ({{ $stations->count() }})</summary>
            <form method="POST" action="{{ route('events.meals.stations.update', $event) }}" class="border-t border-slate-100 p-6">
                @csrf
                <label class="text-xs font-black uppercase text-slate-500">Station names, one per line</label>
                <textarea name="stations" rows="3" placeholder="Gate A&#10;Gate B&#10;VIP Tent" class="mt-2 w-full rounded-xl border-slate-200 font-mono text-xs">{{ old('stations', $stations->pluck('name')->implode("\n")) }}</textarea>
                <p class="mt-1 text-xs text-slate-400">Leave blank to remove station tracking. Staff pick their station once on the scanner page.</p>
                <button class="mt-4 rounded-xl bg-slate-900 px-5 py-3 text-xs font-black uppercase tracking-wider text-white">Save stations</button>
            </form>
        </details>
    @endcan

    <div class="grid gap-5 lg:grid-cols-2">
        @forelse($meals as $meal)
            <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-black uppercase tracking-wider {{ $meal->isOpen() ? 'text-emerald-600' : 'text-slate-400' }}">{{ $meal->isOpen() ? 'Open now' : 'Closed' }}</p><h3 class="mt-2 text-xl font-black">{{ $meal->name }}</h3><p class="mt-2 text-xs text-slate-500">{{ $meal->opens_at?->format('M j, g:i A') ?? 'No opening time' }} — {{ $meal->closes_at?->format('M j, g:i A') ?? 'No closing time' }}</p></div><span class="rounded-full {{ $meal->isLowStock() ? 'bg-amber-100 text-amber-800' : 'bg-blue-50 text-blue-700' }} px-3 py-1 text-xs font-black">{{ $meal->remainingPortions() }} left</span></div>
                <div class="mt-5 grid grid-cols-3 gap-3 text-center"><div class="rounded-xl bg-slate-50 p-3"><strong class="block text-lg">{{ $meal->total_portions }}</strong><span class="text-[10px] uppercase text-slate-500">Stock</span></div><div class="rounded-xl bg-slate-50 p-3"><strong class="block text-lg">{{ $meal->issuedPortions() }}</strong><span class="text-[10px] uppercase text-slate-500">Issued</span></div><div class="rounded-xl bg-slate-50 p-3"><strong class="block text-lg">{{ $meal->collections_count }}</strong><span class="text-[10px] uppercase text-slate-500">People</span></div></div>
                <div class="mt-5 flex flex-wrap gap-2"><a href="{{ route('events.meals.scanner', [$event, $meal]) }}" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-extrabold text-white">Open food scanner</a>@can('update', $event)<form method="POST" action="{{ route('events.meals.update', [$event, $meal]) }}">@csrf @method('PATCH')<input type="hidden" name="name" value="{{ $meal->name }}"><input type="hidden" name="total_portions" value="{{ $meal->total_portions }}"><input type="hidden" name="opens_at" value="{{ $meal->opens_at?->format('Y-m-d H:i:s') }}"><input type="hidden" name="closes_at" value="{{ $meal->closes_at?->format('Y-m-d H:i:s') }}"><input type="hidden" name="low_stock_threshold" value="{{ $meal->low_stock_threshold }}"><input type="hidden" name="entitlements" value="{{ $meal->entitlements->map(fn($e) => $e->category.':'.$e->portions_allowed)->implode("\n") }}"><input type="hidden" name="is_active" value="{{ $meal->is_active ? 0 : 1 }}"><button class="rounded-xl border border-slate-200 px-4 py-3 text-xs font-extrabold text-slate-600">{{ $meal->is_active ? 'Pause' : 'Activate' }}</button></form>@endcan</div>
                @can('update', $event)
                    <details class="mt-5 border-t border-slate-100 pt-4">
                        <summary class="cursor-pointer text-xs font-black text-slate-500">Edit name, stock, entitlements, or serving time</summary>
                        <form method="POST" action="{{ route('events.meals.update', [$event, $meal]) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
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
                    </details>
                    <details class="mt-3 border-t border-slate-100 pt-4">
                        <summary class="cursor-pointer text-xs font-black text-slate-500">Log waste</summary>
                        <form method="POST" action="{{ route('events.meals.waste', [$event, $meal]) }}" class="mt-4 grid gap-3 sm:grid-cols-[100px_1fr_auto]">
                            @csrf
                            <input name="quantity" type="number" min="1" placeholder="Qty" required class="rounded-lg border-slate-200 text-sm">
                            <input name="reason" placeholder="Reason (spoiled, over-prepared, dropped...)" required class="rounded-lg border-slate-200 text-sm">
                            <button class="rounded-lg bg-rose-600 px-4 py-2 text-xs font-black text-white">Log waste</button>
                        </form>
                    </details>
                @endcan
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 p-12 text-center text-sm text-slate-500 lg:col-span-2">No food distributions have been created for this event.</div>
        @endforelse
    </div>
</x-app-layout>
