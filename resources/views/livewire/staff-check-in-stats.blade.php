<div wire:poll.3s class="grid gap-4 sm:grid-cols-3">
    <x-summary-card title="Assigned staff" :value="$assigned" color="blue" />
    <x-summary-card title="Checked in" :value="$checkedIn" color="green" />
    <x-summary-card title="Yet to check in" :value="max(0, $assigned - $checkedIn)" color="red" />
</div>
