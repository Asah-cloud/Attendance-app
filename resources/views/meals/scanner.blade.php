<x-app-layout>
    <x-slot name="header">Food QR Scanner</x-slot>

    <x-event-closed-banner :event="$event" />

    <div id="low-stock-banner" class="mb-5 hidden rounded-xl bg-amber-50 p-4 font-bold text-amber-800">Stock is running low for this distribution.</div>

    <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start"><div><p class="text-xs font-black uppercase tracking-wider text-blue-600">{{ $event->title }}</p><h1 class="mt-2 text-3xl font-black">{{ $meal->name }}</h1><p class="mt-2 text-sm text-slate-500">Scan the attendee's existing event QR code.</p></div><div class="rounded-2xl bg-blue-50 px-5 py-3 text-center"><strong class="block text-2xl text-blue-800" id="remaining-count">{{ $meal->remainingPortions() }}</strong><span class="text-[10px] font-black uppercase text-blue-600">Portions left</span></div></div>

            @if($stations->isNotEmpty())
                <div class="mt-6"><label for="station-select" class="text-xs font-black uppercase text-slate-500">Sharing point</label><select id="station-select" class="mt-2 w-full rounded-xl border-slate-300"><option value="">Choose sharing point</option>@foreach($stations as $station)@php $stationRemaining = $meal->remainingPortionsAtStation($station->id); @endphp<option value="{{ $station->id }}">{{ $station->name }}{{ $stationRemaining !== null ? ' ('.$stationRemaining.' left)' : '' }}</option>@endforeach</select></div>
            @endif

            <div id="qr-reader" class="mt-7 overflow-hidden rounded-2xl border border-slate-200"></div>
            <div id="scan-result" class="mt-5 hidden rounded-2xl p-5 font-bold" role="status" aria-live="polite"></div>
            <div id="pending-badge" class="mt-3 hidden rounded-xl bg-slate-100 p-3 text-xs font-black text-slate-700">Pending sync: <span id="pending-count">0</span> <button type="button" id="sync-now" class="ml-2 underline">Sync now</button></div>
            <form id="manual-scan" class="mt-6 border-t border-slate-100 pt-6"><label for="registration-code" class="text-xs font-black uppercase text-slate-500">Registration code</label><div class="mt-3 flex flex-col gap-3 sm:flex-row"><input id="registration-code" class="min-w-0 flex-1 rounded-xl border-slate-300" placeholder="Scan or paste code" required><button class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-extrabold text-white">Issue portion</button></div></form>

            <form method="GET" class="mt-7 border-t border-slate-100 pt-6"><label class="text-xs font-black uppercase text-slate-500">Find attendee manually</label><div class="mt-3 flex gap-3"><input name="q" value="{{ request('q') }}" class="min-w-0 flex-1 rounded-xl border-slate-300" placeholder="Participant name"><button class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-extrabold">Search</button></div></form>
            @if($errors->any())<p class="my-4 rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</p>@endif
            <p class="mt-4 text-sm"><a class="font-bold text-blue-700" href="{{ route('audit.approvals.index', $event) }}">Approval codes</a> — <a class="text-blue-700" href="{{ route('events.meals.index', $event) }}">Food dashboard</a></p>
            @if(request()->filled('q'))
            <div class="mt-4 divide-y rounded-xl border">
                @forelse($matches as $registration)
                @php $reached = $registration->mealCollections->sum('quantity') >= $meal->entitlementFor($registration->participant->category); @endphp
                <div class="p-4">
                    <p class="font-bold">{{ $registration->participant->name }}</p>
                    <p class="text-xs text-slate-500">{{ $registration->registration_code }} — Confirmed</p>
                    @if($registration->participant->dietary_notes)<p class="text-sm text-emerald-700">{{ $registration->participant->dietary_notes }}</p>@endif
                    <p class="my-2 text-xs">{{ $registration->mealCollections->sum('quantity') }} portion(s) already collected for this meal.</p>
                    <form method="POST" action="{{ route('events.meals.issue', [$event, $meal]) }}" class="meal-action-form mt-3 flex flex-wrap gap-2">
                        @csrf
                        <input type="hidden" name="registration_code" value="{{ $registration->registration_code }}">
                        <input type="hidden" name="meal_station_id" class="selected-station">
                        @if($reached)
                            @if(auth()->user()->can('manageMeals', $event) || auth()->user()->isAuditStaff())
                            <input type="hidden" name="override" value="1">
                            <input name="override_reason" required maxlength="500" placeholder="Reason for extra portion" class="rounded-lg border-slate-300">
                            @cannot('manageMeals', $event)<input name="approval_code" required autocomplete="off" placeholder="Audit Head approval code" class="rounded-lg border-slate-300">@endcannot
                            <button class="rounded-lg bg-amber-500 px-4 py-2 font-bold">Issue approved extra portion</button>
                            @else<p>Entitlement reached. Ask your manager for assistance.</p>@endif
                        @else
                            <button class="rounded-lg bg-blue-600 px-4 py-2 font-bold text-white">Issue portion</button>
                        @endif
                    </form>
                </div>
                @empty<p class="p-4">No confirmed participant found.</p>@endforelse
            </div>
            @endif
        </section>

        <aside class="rounded-3xl border bg-white p-6">
            <h2 class="font-black">Recent collections</h2>
            <div class="mt-4 divide-y">
            @forelse($recent as $collection)
                <div class="py-4">
                    <p class="font-bold">{{ $collection->participant->name }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $collection->quantity }} portion(s) — {{ $collection->collected_at->format('g:i A') }} — {{ $collection->station?->name }}</p>
                    @if($collection->was_overridden)<p class="my-2 text-xs text-amber-700">Override: {{ $collection->override_reason }}</p>@endif
                    @if(auth()->user()->can('manageMeals', $event) || auth()->user()->isAuditStaff())
                    <form method="POST" action="{{ route('events.meals.collections.reverse', [$event, $meal, $collection]) }}" class="mt-3 grid gap-2">
                        @csrf @method('DELETE')
                        <input name="reason" required maxlength="500" placeholder="Reason for reversal" class="rounded-lg border-slate-300 text-xs">
                        @cannot('manageMeals', $event)<input name="approval_code" required autocomplete="off" placeholder="Audit Head approval code" class="rounded-lg border-slate-300 text-xs">@endcannot
                        <button class="text-left text-xs font-bold text-red-600">Reverse one portion</button>
                    </form>
                    @endif
                </div>
            @empty<p class="py-5">Nothing issued yet.</p>@endforelse
            </div>
        </aside>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            const mealId = @js($meal->id);
            const queueKey = `meal-queue-${@js(auth()->id())}-${mealId}`;
            const stationKey = @js('meal-station-'.auth()->id().'-'.$event->id);
            const result = document.getElementById('scan-result');
            const input = document.getElementById('registration-code');
            const stationSelect = document.getElementById('station-select');
            const pendingBadge = document.getElementById('pending-badge');
            const pendingCount = document.getElementById('pending-count');
            const lowStockBanner = document.getElementById('low-stock-banner');
            let busy = false;

            if (stationSelect) {
                const saved = localStorage.getItem(stationKey);
                if (saved && Array.from(stationSelect.options).some(option => option.value === saved)) stationSelect.value = saved;
                if (!stationSelect.value && stationSelect.options.length === 2) stationSelect.selectedIndex = 1;
                stationSelect.addEventListener('change', () => localStorage.setItem(stationKey, stationSelect.value));
            }

            document.querySelectorAll('.meal-action-form').forEach(form => form.addEventListener('submit', () => {
                form.querySelector('.selected-station').value = stationSelect?.value || '';
            }));

            const showResult = (message, successful) => { result.classList.remove('hidden'); result.textContent = message; result.className = `mt-5 rounded-2xl p-5 font-bold ${successful ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800'}`; };

            const getQueue = () => { try { return JSON.parse(localStorage.getItem(queueKey) || '[]'); } catch (e) { return []; } };
            const setQueue = items => { localStorage.setItem(queueKey, JSON.stringify(items)); pendingCount.textContent = items.length; pendingBadge.classList.toggle('hidden', items.length === 0); };
            const enqueue = item => setQueue([...getQueue(), item]);

            const submitScan = async payload => {
                const response = await fetch(@js(route('events.meals.issue', [$event, $meal])), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                    body: JSON.stringify(payload),
                });
                let data = {};
                try { data = await response.json(); } catch (e) { /* no body */ }
                return { ok: response.ok, status: response.status, data };
            };

            const failedSync = [];
            const showFailedSync = () => {
                if (failedSync.length === 0) return;
                showResult(`${failedSync.length} queued scan(s) could not be synced and need manual review: ${failedSync.map(f => f.registration_code).join(', ')}`, false);
            };

            const flushQueue = async () => {
                let queue = getQueue();
                if (queue.length === 0) return;
                const remaining = [];
                for (const item of queue) {
                    try {
                        const { ok, status } = await submitScan(item);
                        if (ok) continue;
                        if (status >= 500) { remaining.push(item); continue; } // server/transient error — retry later
                        failedSync.push(item); // 4xx: conflict/entitlement/closed — needs manual review, not silently dropped
                    } catch (e) {
                        remaining.push(item); // network error — retry later
                    }
                }
                setQueue(remaining);
                showFailedSync();
            };

            const issue = async value => {
                if (busy || !value) return;
                busy = true;
                const payload = { registration_code: value.trim(), scanned_at: new Date().toISOString() };
                if (stationSelect && stationSelect.value) payload.meal_station_id = stationSelect.value;
                try {
                    const { ok, data } = await submitScan(payload);
                    showResult(data.message || 'Unable to process this code.', ok);
                    if (ok) {
                        input.value = '';
                        const count = document.getElementById('remaining-count');
                        count.textContent = Math.max(0, Number(count.textContent) - 1);
                    }
                } catch (e) {
                    enqueue(payload);
                    showResult('Offline — scan queued and will sync automatically once you are back online.', true);
                    input.value = '';
                } finally {
                    window.setTimeout(() => busy = false, 1200);
                }
            };

            document.getElementById('manual-scan').addEventListener('submit', event => { event.preventDefault(); issue(input.value); });
            document.getElementById('sync-now').addEventListener('click', flushQueue);
            window.addEventListener('online', flushQueue);
            setQueue(getQueue());

            const pollStatus = async () => {
                try {
                    const response = await fetch(@js(route('events.meals.status', [$event, $meal])), { headers: { 'Accept': 'application/json' } });
                    if (!response.ok) return;
                    const data = await response.json();
                    document.getElementById('remaining-count').textContent = data.remaining;
                    lowStockBanner.classList.toggle('hidden', !data.low_stock);
                } catch (e) { /* stay quiet, offline */ }
                flushQueue();
            };
            setInterval(pollStatus, 10000);

            if (window.loadHtml5Qrcode) { const Html5Qrcode = await window.loadHtml5Qrcode(); const scanner = new Html5Qrcode('qr-reader'); scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 240, height: 240 } }, issue).catch(() => showResult('Camera unavailable. Enter the registration code or search below.', false)); }
        });
    </script>
    @endpush
</x-app-layout>
