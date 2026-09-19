<div wire:poll.5s>
    @php $remaining = max(0, $meal->total_portions - $issued); @endphp
    <div class="flex justify-end"><span class="rounded-full {{ $meal->low_stock_threshold !== null && $remaining <= $meal->low_stock_threshold ? 'bg-amber-100 text-amber-800' : 'bg-blue-50 text-blue-700' }} px-3 py-1 text-xs font-black">{{ $remaining }} left</span></div>
    <div class="mt-3 grid grid-cols-3 gap-3 text-center"><div class="rounded-xl bg-slate-50 p-3"><strong class="block text-lg">{{ $meal->total_portions }}</strong><span class="text-[10px] uppercase text-slate-500">Stock</span></div><div class="rounded-xl bg-slate-50 p-3"><strong class="block text-lg">{{ $issued }}</strong><span class="text-[10px] uppercase text-slate-500">Issued</span></div><div class="rounded-xl bg-slate-50 p-3"><strong class="block text-lg">{{ $people }}</strong><span class="text-[10px] uppercase text-slate-500">People</span></div></div>
    <p class="mt-3 text-sm text-slate-600">{{ max(0, $confirmed - $people) }} confirmed participants yet to collect this meal.</p>
</div>
