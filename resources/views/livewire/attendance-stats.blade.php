<div wire:poll.3s class="space-y-4">
    <div class="group rounded-3xl bg-blue-900 p-8 text-white shadow-xl transition-all duration-300 hover:shadow-2xl">
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

    <div class="rounded-3xl border border-amber-200 bg-amber-50 p-8 shadow-sm">
        <h3 class="text-xs font-black uppercase tracking-widest text-amber-800">Participant staff present</h3>
        <div class="mt-4 flex items-end justify-between gap-4">
            <p class="text-sm leading-6 text-amber-900/70">Participant 1, Participant 2, and similarly numbered staff stay present for the whole event after check-in.</p>
            <span class="shrink-0 text-3xl font-black text-amber-900">{{ number_format($participantStaffCount) }}</span>
        </div>
    </div>
</div>
