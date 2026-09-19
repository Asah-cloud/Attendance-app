<div wire:poll.3s class="group rounded-3xl bg-blue-900 p-8 text-white shadow-xl transition-all duration-300 hover:shadow-2xl">
    <h3 class="mb-6 text-xs font-bold uppercase tracking-widest text-blue-300">Current Stats</h3>
    <div class="space-y-4">
        <div class="flex items-end justify-between border-b border-white/10 pb-2">
            <span class="text-sm text-blue-100/70">Eligible attendees</span>
            <span class="text-2xl font-black">{{ number_format($totalMembers) }}</span>
        </div>
        <div class="flex items-end justify-between">
            <span class="text-sm text-blue-100/70">Present Today</span>
            <span class="text-2xl font-black text-green-400">{{ number_format($presentCount) }}</span>
        </div>
    </div>
</div>
