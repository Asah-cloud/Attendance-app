<x-app-layout>
    <x-slot name="header">Edit History</x-slot>
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black text-slate-900">{{ $registration->participant->name }}</h1>
                <p class="text-sm text-slate-500">Every change made to this attendee's details, most recent first.</p>
            </div>
            <a href="{{ route('events.registrations.index', $event) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-extrabold text-slate-600 hover:border-blue-300 hover:text-blue-700">Back to attendees</a>
        </div>

        <div class="space-y-4">
            @forelse($logs as $log)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs font-bold uppercase tracking-wider text-slate-400">
                        <span>{{ $log->user?->name ?? 'Unknown user' }}</span>
                        <span>{{ $log->created_at->format('M j, Y g:i A') }}</span>
                    </div>
                    <div class="mt-3 space-y-2">
                        @foreach($log->changes as $field => $change)
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-black uppercase tracking-wide text-slate-600">{{ str_replace('_', ' ', $field) }}</span>
                                <span class="text-slate-400 line-through">{{ $change['old'] ?: '—' }}</span>
                                <span class="text-slate-400">&rarr;</span>
                                <span class="font-bold text-slate-900">{{ $change['new'] ?: '—' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center text-slate-500">No edits recorded for this attendee yet.</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
