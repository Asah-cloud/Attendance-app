<div wire:poll.10s class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <x-summary-card title="Total" :value="$total" color="blue" />
    <x-summary-card title="Confirmed" :value="$confirmed" color="green" />
    <x-summary-card title="Pending" :value="$pending" color="amber" />
    <x-summary-card title="Waitlisted" :value="$waitlisted" color="purple" />
</div>
