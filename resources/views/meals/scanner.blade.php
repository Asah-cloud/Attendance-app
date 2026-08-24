<x-app-layout>
    <x-slot name="header">Food QR Scanner</x-slot>

    @if(session('success'))<div class="mb-5 rounded-xl bg-emerald-50 p-4 font-bold text-emerald-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-5 rounded-xl bg-red-50 p-4 font-bold text-red-800">{{ session('error') }}</div>@endif

    <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start"><div><p class="text-xs font-black uppercase tracking-wider text-blue-600">{{ $event->title }}</p><h1 class="mt-2 text-3xl font-black">{{ $meal->name }}</h1><p class="mt-2 text-sm text-slate-500">Scan the attendee’s existing event QR code.</p></div><div class="rounded-2xl bg-blue-50 px-5 py-3 text-center"><strong class="block text-2xl text-blue-800" id="remaining-count">{{ $meal->remainingPortions() }}</strong><span class="text-[10px] font-black uppercase text-blue-600">Portions left</span></div></div>

            <div id="qr-reader" class="mt-7 overflow-hidden rounded-2xl border border-slate-200"></div>
            <div id="scan-result" class="mt-5 hidden rounded-2xl p-5 font-bold" role="status" aria-live="polite"></div>
            <form id="manual-scan" class="mt-6 border-t border-slate-100 pt-6"><label for="registration-code" class="text-xs font-black uppercase text-slate-500">Registration code</label><div class="mt-3 flex flex-col gap-3 sm:flex-row"><input id="registration-code" class="min-w-0 flex-1 rounded-xl border-slate-300" placeholder="Scan or paste code" required><button class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-extrabold text-white">Issue portion</button></div></form>

            <form method="GET" class="mt-7 border-t border-slate-100 pt-6"><label class="text-xs font-black uppercase text-slate-500">Find attendee manually</label><div class="mt-3 flex gap-3"><input name="q" value="{{ request('q') }}" class="min-w-0 flex-1 rounded-xl border-slate-300" placeholder="Name, email, or phone"><button class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-extrabold">Search</button></div></form>
            @if(request()->filled('q'))<div class="mt-4 divide-y divide-slate-100 rounded-2xl border border-slate-200">@forelse($matches as $registration)<div class="flex flex-col justify-between gap-3 p-4 sm:flex-row sm:items-center"><div><p class="font-extrabold">{{ $registration->participant->name }}</p><p class="text-xs text-slate-500">{{ $registration->participant->email ?: $registration->participant->phone }}</p></div>@if($registration->mealCollections->isNotEmpty())@can('update', $event)<form method="POST" action="{{ route('events.meals.issue', [$event, $meal]) }}" class="flex flex-col gap-2 sm:flex-row">@csrf<input type="hidden" name="registration_code" value="{{ $registration->registration_code }}"><input type="hidden" name="override" value="1"><input name="override_reason" required maxlength="500" placeholder="Reason for extra portion" class="rounded-lg border-slate-200 text-xs"><button class="rounded-lg bg-amber-500 px-3 py-2 text-xs font-black text-white">Manager override</button></form>@else<span class="text-xs font-black text-amber-700">Already collected</span>@endcan @else<form method="POST" action="{{ route('events.meals.issue', [$event, $meal]) }}">@csrf<input type="hidden" name="registration_code" value="{{ $registration->registration_code }}"><button class="rounded-xl bg-blue-600 px-4 py-2 text-xs font-black text-white">Issue</button></form>@endif</div>@empty<p class="p-5 text-sm text-slate-500">No confirmed attendee found.</p>@endforelse</div>@endif
        </section>

        <aside class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><div class="flex items-center justify-between"><h2 class="font-black">Recent collections</h2><a href="{{ route('events.meals.index', $event) }}" class="text-xs font-bold text-blue-700">All meals</a></div><div class="mt-4 divide-y divide-slate-100">@forelse($recent as $collection)<div class="py-4"><div class="flex justify-between gap-3"><div><p class="text-sm font-bold">{{ $collection->participant->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $collection->quantity }} portion(s) · {{ $collection->collected_at->format('g:i A') }}</p></div>@can('update', $event)<form method="POST" action="{{ route('events.meals.collections.reverse', [$event, $meal, $collection]) }}">@csrf @method('DELETE')<button class="text-xs font-bold text-red-600">Reverse</button></form>@endcan</div>@if($collection->was_overridden)<p class="mt-2 text-xs text-amber-700">Override: {{ $collection->override_reason }}</p>@endif</div>@empty<p class="py-8 text-center text-sm text-slate-500">Nothing issued yet.</p>@endforelse</div></aside>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            const result = document.getElementById('scan-result'); const input = document.getElementById('registration-code'); let busy = false;
            const showResult = (message, successful) => { result.textContent = message; result.className = `mt-5 rounded-2xl p-5 font-bold ${successful ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800'}`; };
            const issue = async value => { if (busy || !value) return; busy = true; try { const response = await fetch(@js(route('events.meals.issue', [$event, $meal])), {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':@js(csrf_token())}, body:JSON.stringify({registration_code:value.trim()})}); const data = await response.json(); showResult(data.message || 'Unable to process this code.', response.ok); if(response.ok){input.value=''; const count=document.getElementById('remaining-count'); count.textContent=Math.max(0, Number(count.textContent)-1);} } catch(e){showResult('Could not connect. Please try again.', false);} finally {window.setTimeout(()=>busy=false,1200);} };
            document.getElementById('manual-scan').addEventListener('submit', event => {event.preventDefault(); issue(input.value);});
            if(window.loadHtml5Qrcode){const Html5Qrcode=await window.loadHtml5Qrcode(); const scanner=new Html5Qrcode('qr-reader'); scanner.start({facingMode:'environment'},{fps:10,qrbox:{width:240,height:240}},issue).catch(()=>showResult('Camera unavailable. Enter the registration code or search below.',false));}
        });
    </script>
    @endpush
</x-app-layout>
