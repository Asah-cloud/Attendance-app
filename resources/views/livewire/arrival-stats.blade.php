<div wire:poll.3s>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-summary-card title="Expected" :value="$confirmed" color="blue" />
        <x-summary-card title="Arrived" :value="$arrived" color="green" />
        <x-summary-card title="Yet to arrive" :value="max(0, $confirmed - $arrived)" color="red" />
    </div>
    @if($unusedPlaceholders)
        <p class="mt-3 text-sm text-slate-500">{{ number_format($unusedPlaceholders) }} unused numbered {{ \Illuminate\Support\Str::plural('badge', $unusedPlaceholders) }} ("Participant 12" and so on) not counted. Each one joins the expected total when it is scanned.</p>
    @endif
</div>
