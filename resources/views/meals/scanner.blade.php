<x-app-layout>
    <x-slot name="header">Food QR Scanner</x-slot>

    <x-event-closed-banner :event="$event" />

    <div id="low-stock-banner" class="mb-5 hidden rounded-xl bg-amber-50 p-4 font-bold text-amber-800">Stock is running low for this distribution.</div>

    <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start"><div><p class="text-xs font-black uppercase tracking-wider text-blue-600">{{ $event->title }}</p><h1 class="mt-2 text-3xl font-black">{{ $meal->name }}</h1><p class="mt-2 text-sm text-slate-500">Managers can scan an attendee or event staff badge QR code.</p></div><div class="rounded-2xl bg-blue-50 px-5 py-3 text-center"><strong class="block text-2xl text-blue-800" id="remaining-count">{{ $meal->remainingPortions() }}</strong><span class="text-[10px] font-black uppercase text-blue-600">Portions left</span></div></div>

            @if($stations->isNotEmpty())
                <div class="mt-6"><label for="station-select" class="text-xs font-black uppercase text-slate-500">Sharing point</label><select id="station-select" class="mt-2 w-full rounded-xl border-slate-300"><option value="">Choose sharing point</option>@foreach($stations as $station)@php $stationRemaining = $meal->remainingPortionsAtStation($station->id); @endphp<option value="{{ $station->id }}">{{ $station->name }}{{ $stationRemaining !== null ? ' ('.$stationRemaining.' left)' : '' }}</option>@endforeach</select></div>
            @endif

            <div class="mt-6 flex justify-end">
                <button type="button" id="sound-toggle" aria-pressed="true" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-black text-slate-700">Sound and voice: On</button>
            </div>
            <div id="qr-reader" class="mt-3 overflow-hidden rounded-2xl border border-slate-200"></div>
            <div id="scan-result" class="mt-5 hidden rounded-2xl p-5 font-bold" role="status" aria-live="polite"></div>
            <div id="pending-badge" class="mt-3 hidden rounded-xl bg-slate-100 p-3 text-xs font-black text-slate-700">Pending sync: <span id="pending-count">0</span> <button type="button" id="sync-now" class="ml-2 underline">Sync now</button></div>
            <form id="manual-scan" class="mt-6 border-t border-slate-100 pt-6"><label for="registration-code" class="text-xs font-black uppercase text-slate-500">Registration code</label><div class="mt-3 flex flex-col gap-3 sm:flex-row"><input id="registration-code" class="min-w-0 flex-1 rounded-xl border-slate-300" placeholder="Scan or paste code" required><button class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-extrabold text-white">Issue portion</button></div></form>

            <form method="GET" data-live-search="#meal-search-results" class="mt-7 border-t border-slate-100 pt-6"><label class="text-xs font-black uppercase text-slate-500">Find attendee manually</label><div class="mt-3 flex gap-3"><input name="q" type="search" autocomplete="off" value="{{ request('q') }}" class="min-w-0 flex-1 rounded-xl border-slate-300" placeholder="Participant name"><button class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-extrabold">Search</button></div></form>
            @if($errors->any())<p class="my-4 rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</p>@endif
            <p class="mt-4 text-sm"><a class="font-bold text-blue-700" href="{{ route('audit.approvals.index', $event) }}">Approval codes</a> — <a class="text-blue-700" href="{{ route('events.meals.index', $event) }}">Food dashboard</a></p>
            <div id="meal-search-results">
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
            </div>
        </section>

        <livewire:meal-recent-collections :event="$event" :meal="$meal" />
    </div>

    {{-- Covers the whole screen after every scan, so the result can't be missed below the camera. --}}
    <div id="scan-overlay" class="fixed inset-0 z-[70] hidden flex-col items-center justify-center p-6 text-center" role="alertdialog" aria-modal="true" aria-labelledby="scan-overlay-title" aria-describedby="scan-overlay-detail">
        <div id="scan-overlay-icon" class="grid h-28 w-28 place-items-center rounded-full bg-white/25 text-7xl font-black leading-none" aria-hidden="true"></div>
        <h2 id="scan-overlay-title" class="mt-6 break-words text-3xl font-black uppercase tracking-wide sm:text-6xl"></h2>
        <p id="scan-overlay-name" class="mt-4 max-w-2xl break-words text-2xl font-extrabold sm:text-4xl"></p>
        <p id="scan-overlay-detail" class="mt-3 max-w-xl text-base font-bold opacity-90 sm:text-lg"></p>
        <button type="button" id="scan-overlay-close" class="mt-10 rounded-2xl bg-white px-10 py-4 text-lg font-black text-slate-900 shadow-lg">Next person</button>
        <p id="scan-overlay-hint" class="mt-3 text-sm font-bold opacity-80"></p>
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

            // Delegated, so it also covers result forms that a live search adds to the page later.
            document.addEventListener('submit', event => {
                const form = event.target.closest?.('.meal-action-form');
                if (form) form.querySelector('.selected-station').value = stationSelect?.value || '';
            });

            const showResult = (message, successful) => { result.classList.remove('hidden'); result.textContent = message; result.className = `mt-5 rounded-2xl p-5 font-bold ${successful ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800'}`; };

            // ---- Full-screen result, sound, voice and vibration ----
            const soundKey = 'meal-scanner-sound';
            const soundToggle = document.getElementById('sound-toggle');
            let soundOn = true;
            try { soundOn = localStorage.getItem(soundKey) !== 'off'; } catch (e) { /* private mode */ }
            const paintSoundToggle = () => { soundToggle.textContent = `Sound and voice: ${soundOn ? 'On' : 'Off'}`; soundToggle.setAttribute('aria-pressed', soundOn ? 'true' : 'false'); };
            paintSoundToggle();
            soundToggle.addEventListener('click', () => { soundOn = !soundOn; try { localStorage.setItem(soundKey, soundOn ? 'on' : 'off'); } catch (e) { /* ignore */ } paintSoundToggle(); });

            let audio = null;
            // Phones only allow sound after the user has touched the page once, so unlock it on the first tap.
            const unlockAudio = () => {
                try {
                    audio ??= new (window.AudioContext || window.webkitAudioContext)();
                    if (audio.state === 'suspended') audio.resume();
                    if (window.speechSynthesis) window.speechSynthesis.speak(new SpeechSynthesisUtterance(''));
                } catch (e) { /* no audio support */ }
            };
            document.addEventListener('pointerdown', unlockAudio, { once: true });

            const tone = (frequency, start, length, type = 'sine', volume = 0.35) => {
                const oscillator = audio.createOscillator();
                const gain = audio.createGain();
                oscillator.type = type;
                oscillator.frequency.value = frequency;
                gain.gain.setValueAtTime(volume, audio.currentTime + start);
                gain.gain.exponentialRampToValueAtTime(0.001, audio.currentTime + start + length);
                oscillator.connect(gain).connect(audio.destination);
                oscillator.start(audio.currentTime + start);
                oscillator.stop(audio.currentTime + start + length);
            };
            const speak = text => {
                if (!text || !window.speechSynthesis) return;
                window.speechSynthesis.cancel();
                const utterance = new SpeechSynthesisUtterance(text);
                utterance.rate = 1.05;
                window.speechSynthesis.speak(utterance);
            };
            const feedback = kind => {
                if (navigator.vibrate) navigator.vibrate(kind === 'ok' ? 120 : [400, 120, 400, 120, 400]);
                if (!soundOn) return;
                try {
                    audio ??= new (window.AudioContext || window.webkitAudioContext)();
                    if (kind === 'ok') { tone(880, 0, 0.12); tone(1320, 0.13, 0.18); }
                    else if (kind === 'warn') { tone(660, 0, 0.2, 'triangle'); tone(660, 0.25, 0.2, 'triangle'); }
                    else { tone(220, 0, 0.25, 'square', 0.25); tone(220, 0.32, 0.25, 'square', 0.25); tone(165, 0.64, 0.45, 'square', 0.25); }
                } catch (e) { /* no audio support */ }
            };

            const overlay = document.getElementById('scan-overlay');
            const overlayClose = document.getElementById('scan-overlay-close');
            const looks = {
                ok: { bg: 'bg-emerald-700 text-white', icon: '✓' },
                warn: { bg: 'bg-amber-400 text-slate-950', icon: '!' },
                bad: { bg: 'bg-rose-700 text-white', icon: '✕' },
            };
            let overlayOpen = false;
            let overlayTimer = null;
            let lastCode = null;
            let lastClosedAt = 0;
            const hideOverlay = () => {
                window.clearTimeout(overlayTimer);
                overlay.classList.add('hidden');
                overlay.classList.remove('flex');
                overlayOpen = false;
                lastClosedAt = Date.now();
            };
            const showOverlay = ({ kind, title, name, detail, say }) => {
                window.clearTimeout(overlayTimer);
                overlay.className = `fixed inset-0 z-[70] flex flex-col items-center justify-center p-6 text-center ${looks[kind].bg}`;
                document.getElementById('scan-overlay-icon').textContent = looks[kind].icon;
                document.getElementById('scan-overlay-title').textContent = title;
                document.getElementById('scan-overlay-name').textContent = name || '';
                document.getElementById('scan-overlay-detail').textContent = detail || '';
                // Green clears itself so a queue keeps moving; red and amber wait for a tap so nobody misses them.
                document.getElementById('scan-overlay-hint').textContent = kind === 'ok' ? 'Closes by itself' : 'Tap to continue';
                overlayOpen = true;
                feedback(kind);
                if (soundOn) speak(say);
                overlayClose.focus({ preventScroll: true });
                if (kind === 'ok') overlayTimer = window.setTimeout(hideOverlay, 1800);
            };
            overlayClose.addEventListener('click', hideOverlay);
            overlay.addEventListener('click', event => { if (event.target === overlay) hideOverlay(); });
            document.addEventListener('keydown', event => { if (overlayOpen && (event.key === 'Escape' || event.key === 'Enter')) { event.preventDefault(); hideOverlay(); } });

            const describe = (data, ok) => {
                const name = data.name || '';
                switch (data.outcome) {
                    case 'served': return { kind: 'ok', title: 'Served', name, detail: '1 portion issued' };
                    case 'extra_portion': return { kind: 'ok', title: 'Extra portion', name, detail: 'Approved extra portion issued' };
                    case 'already_collected': return { kind: 'bad', title: 'Already served', name, detail: `Collected at ${data.collected_at}. Do not serve again.`, say: `Already served at ${data.collected_at}` };
                    case 'out_of_stock': return { kind: 'bad', title: 'No food left', name, detail: data.message, say: 'No food left here' };
                    case 'not_open': return { kind: 'bad', title: 'Food not open', name, detail: data.message, say: 'Food is not open' };
                    case 'invalid': return { kind: 'bad', title: 'Not valid', name, detail: data.message, say: 'Not valid' };
                    default: return ok
                        ? { kind: 'ok', title: 'Served', name, detail: data.message }
                        : { kind: 'bad', title: 'Not served', name, detail: data.message || 'Unable to process this code.', say: 'Not served' };
                }
            };

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
                const failedBefore = failedSync.length;
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
                const newlyFailed = failedSync.length - failedBefore;
                if (newlyFailed > 0 && !overlayOpen) {
                    showOverlay({ kind: 'bad', title: 'Check offline scans', name: `${newlyFailed} scan(s) not accepted`, detail: `Saved while offline, then refused when synced (for example, already served): ${failedSync.slice(failedBefore).map(f => f.registration_code).join(', ')}. Tell your supervisor.`, say: 'Some offline scans were refused' });
                }
            };

            const issue = async (value, fromCamera = false) => {
                if (busy || overlayOpen || !value) return;
                const code = value.trim();
                // The badge is often still in front of the camera after the result closes; don't scan it again straight away.
                if (fromCamera && code === lastCode && Date.now() - lastClosedAt < 3000) return;
                busy = true;
                lastCode = code;
                const payload = { registration_code: code, scanned_at: new Date().toISOString() };
                if (stationSelect && stationSelect.value) payload.meal_station_id = stationSelect.value;
                try {
                    const { ok, data } = await submitScan(payload);
                    showResult(data.message || 'Unable to process this code.', ok);
                    showOverlay(describe(data, ok));
                    if (ok) {
                        input.value = '';
                        const count = document.getElementById('remaining-count');
                        count.textContent = Math.max(0, Number(count.textContent) - 1);
                    }
                } catch (e) {
                    enqueue(payload);
                    showResult('Offline — scan saved and will be checked once you are back online. It could not be checked for an earlier collection.', false);
                    showOverlay({ kind: 'warn', title: 'Saved offline', name: code, detail: 'No connection, so this badge could not be checked for an earlier collection. Check the badge or ask your supervisor before serving.', say: 'Offline. Could not check' });
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

            if (window.loadHtml5Qrcode) { const Html5Qrcode = await window.loadHtml5Qrcode(); const scanner = new Html5Qrcode('qr-reader'); scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 240, height: 240 } }, decoded => issue(decoded, true)).catch(() => showResult('Camera unavailable. Enter the registration code or search below.', false)); }
        });
    </script>
    @endpush
</x-app-layout>
