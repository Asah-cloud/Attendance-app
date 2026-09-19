<aside wire:poll.5s class="rounded-3xl border bg-white p-6">
    <h2 class="font-black">Recent collections</h2>
    <div class="mt-4 divide-y">
        @forelse($collections as $collection)
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
