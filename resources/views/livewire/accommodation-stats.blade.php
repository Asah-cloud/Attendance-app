<div wire:poll.10s class="grid gap-4 sm:grid-cols-4">
    <x-summary-card title="Beds" :value="$beds" color="blue" />
    <x-summary-card title="Rooms" :value="$rooms" color="green" />
    <x-summary-card title="Need rooms" :value="$required" color="amber" />
    <x-summary-card title="Allocated" :value="$assigned" color="purple" />
</div>
