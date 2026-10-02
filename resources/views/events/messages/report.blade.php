<x-app-layout>
    <x-slot name="header">Message report</x-slot>
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8 print:px-0 print:py-0">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3 print:hidden">
            <div>
                <a href="{{ route('events.messages.index', ['event' => $event, 'message' => $message->id]) }}" class="text-xs font-bold text-slate-500 hover:text-slate-800">&larr; Back to messages</a>
                <h1 class="mt-2 text-2xl font-black text-slate-900">{{ $message->subject ?: 'Message #'.$message->id }}</h1>
                <p class="text-sm text-slate-500">{{ $event->title }} &middot; {{ $message->creator?->name ?? 'A manager' }} &middot; {{ ($message->sent_at ?? $message->created_at)->format('M j, Y g:i A') }} &middot; {{ str_replace('_', ' ', $message->mode) }} mode</p>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="window.print()" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-black uppercase tracking-widest text-slate-700 hover:bg-slate-100">Print</button>
                <a href="{{ route('events.messages.report.csv', [$event, $message]) }}" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-black uppercase tracking-widest text-white hover:bg-emerald-700">Download CSV</a>
            </div>
        </div>

        <div class="mb-6 hidden print:block">
            <h1 class="text-xl font-black text-slate-900">{{ $message->subject ?: 'Message #'.$message->id }}</h1>
            <p class="text-sm text-slate-500">{{ $event->title }} &middot; {{ $message->creator?->name ?? 'A manager' }} &middot; {{ ($message->sent_at ?? $message->created_at)->format('M j, Y g:i A') }}</p>
        </div>

        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-5">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 text-center"><p class="text-2xl font-black text-slate-900">{{ $recipients->count() }}</p><p class="text-[11px] font-black uppercase tracking-widest text-slate-400">Recipients</p></div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-center"><p class="text-2xl font-black text-emerald-700">{{ $counts['sent'] }}</p><p class="text-[11px] font-black uppercase tracking-widest text-emerald-600">Sent</p></div>
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-center"><p class="text-2xl font-black text-rose-700">{{ $counts['failed'] }}</p><p class="text-[11px] font-black uppercase tracking-widest text-rose-600">Failed</p></div>
            <div class="rounded-2xl border border-slate-200 bg-slate-100 p-4 text-center"><p class="text-2xl font-black text-slate-600">{{ $counts['skipped'] }}</p><p class="text-[11px] font-black uppercase tracking-widest text-slate-500">Skipped</p></div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-center"><p class="text-2xl font-black text-amber-700">{{ $counts['pending'] }}</p><p class="text-[11px] font-black uppercase tracking-widest text-amber-600">Pending</p></div>
        </div>
        <p class="mb-6 text-xs font-bold text-slate-500">{{ $channelCounts['mail'] }} delivered by email &middot; {{ $channelCounts['sms'] }} delivered by SMS</p>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wider text-slate-500">
                    <tr><th class="px-5 py-3">Name</th><th class="px-5 py-3">Email</th><th class="px-5 py-3">Phone</th><th class="px-5 py-3">Channel</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Error</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($recipients as $recipient)
                        @php($statusColors = ['sent' => 'bg-emerald-100 text-emerald-700', 'pending' => 'bg-amber-100 text-amber-700', 'failed' => 'bg-rose-100 text-rose-700', 'skipped' => 'bg-slate-100 text-slate-600'])
                        <tr>
                            <td class="px-5 py-3 font-bold text-slate-900">{{ $recipient->name }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $recipient->email ?: '—' }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $recipient->phone ?: '—' }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $recipient->channel === 'mail' ? 'Email' : ($recipient->channel === 'sms' ? 'SMS' : ($recipient->channel ?: '—')) }}</td>
                            <td class="px-5 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-black uppercase tracking-wide {{ $statusColors[$recipient->status] ?? 'bg-gray-100 text-gray-600' }}">{{ $recipient->status }}</span></td>
                            <td class="px-5 py-3 max-w-xs break-words text-xs text-rose-600">{{ $recipient->error_message }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">No recipients.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
