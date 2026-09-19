<div wire:poll.3s class="grid gap-4 sm:grid-cols-3">
    <x-summary-card title="Confirmed" :value="$confirmed" color="blue" />
    <x-summary-card title="Arrived" :value="$arrived" color="green" />
    <x-summary-card title="Yet to arrive" :value="max(0, $confirmed - $arrived)" color="red" />
</div>
