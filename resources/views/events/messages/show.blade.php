<x-app-layout>
    <x-slot name="header">Message detail</x-slot>

    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-black uppercase tracking-widest text-blue-600">{{ $event->title }}</p>
                <h1 class="mt-1 text-2xl font-black text-slate-900">{{ $message->subject ?: '(no subject)' }}</h1>
                <p class="mt-1 text-xs text-slate-400">Sent {{ $message->created_at->format('M d, Y H:i') }} by {{ $message->creator?->name ?? 'a manager' }} · {{ str_replace('_', ' ', $message->mode) }} mode</p>
            </div>
            <a href="{{ route('events.messages.index', $event) }}" class="text-xs font-bold text-slate-500 underline">Back to history</a>
        </div>

        <div class="mb-8 grid gap-6 md:grid-cols-2">
            @if($message->email_body)
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-widest text-slate-400">Email body</p>
                    <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $message->email_body }}</p>
                    @if(!empty($message->attachments))
                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach($message->attachments as $attachment)
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-bold text-slate-600">{{ $attachment['name'] }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
            @if($message->sms_body)
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-widest text-slate-400">SMS body</p>
                    <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $message->sms_body }}</p>
                </div>
            @endif
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wider text-slate-500">
                <tr><th class="px-5 py-4">Name</th><th class="px-5 py-4">Contact</th><th class="px-5 py-4">Channel</th><th class="px-5 py-4">Status</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($recipients as $recipient)
                    @php($statusColors = ['sent' => 'bg-green-100 text-green-700', 'pending' => 'bg-amber-100 text-amber-700', 'failed' => 'bg-red-100 text-red-700', 'skipped' => 'bg-slate-100 text-slate-600'])
                    <tr>
                        <td class="px-5 py-4 font-bold text-slate-800">{{ $recipient->name }}</td>
                        <td class="px-5 py-4 text-xs text-slate-500">{{ $recipient->email ?: '—' }} · {{ $recipient->phone ?: '—' }}</td>
                        <td class="px-5 py-4 text-xs font-bold uppercase text-slate-500">{{ $recipient->channel === 'sms' ? 'SMS' : ($recipient->channel === 'mail' ? 'Email' : '—') }}</td>
                        <td class="px-5 py-4">
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide {{ $statusColors[$recipient->status] ?? 'bg-gray-100 text-gray-600' }}">{{ $recipient->status }}</span>
                            @if($recipient->error_message)<p class="mt-1 max-w-xs text-[10px] text-red-500">{{ $recipient->error_message }}</p>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-12 text-center text-slate-500">No recipients.</td></tr>
                @endforelse
            </tbody>
        </table></div></div>

        @if($recipients->hasPages())<div class="mt-6">{{ $recipients->links() }}</div>@endif
    </div>
</x-app-layout>
