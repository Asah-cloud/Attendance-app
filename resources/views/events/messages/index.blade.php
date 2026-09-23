<x-app-layout>
    <x-slot name="header">Messages</x-slot>

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black text-slate-900">Custom messages</h1>
                <p class="text-sm text-slate-500">{{ $event->title }} — import a list, write your own message, and send it as email or SMS.</p>
            </div>
            <a href="{{ route('events.messages.create', $event) }}" class="rounded-xl bg-blue-900 px-4 py-2 text-xs font-black uppercase tracking-wider text-white">Compose message</a>
        </div>

        @if(session('success'))
            <div class="mb-6 rounded-2xl border border-green-200 bg-green-50 p-4 text-sm font-semibold text-green-700">{{ session('success') }}</div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wider text-slate-500">
                <tr><th class="px-5 py-4">Subject / Body</th><th class="px-5 py-4">Mode</th><th class="px-5 py-4">Recipients</th><th class="px-5 py-4">Delivery</th><th class="px-5 py-4">Sent</th><th class="px-5 py-4"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($messages as $message)
                    <tr>
                        <td class="px-5 py-4">
                            <div class="font-bold text-slate-900">{{ $message->subject ?: ($message->email_body ? '(no subject)' : '(SMS only)') }}</div>
                            <div class="mt-1 max-w-sm truncate text-xs text-slate-400">{{ $message->email_body ?: $message->sms_body }}</div>
                        </td>
                        <td class="px-5 py-4 text-xs font-bold uppercase text-slate-500">{{ str_replace('_', ' ', $message->mode) }}</td>
                        <td class="px-5 py-4 font-bold text-slate-700">{{ $message->recipient_count }}</td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap gap-1.5 text-[10px] font-black uppercase">
                                <span class="rounded-full bg-green-100 px-2 py-1 text-green-700">{{ $message->sent_count }} sent</span>
                                @if($message->failed_count)<span class="rounded-full bg-red-100 px-2 py-1 text-red-700">{{ $message->failed_count }} failed</span>@endif
                                @if($message->pending_count)<span class="rounded-full bg-amber-100 px-2 py-1 text-amber-700">{{ $message->pending_count }} pending</span>@endif
                                @if($message->skipped_count)<span class="rounded-full bg-slate-100 px-2 py-1 text-slate-600">{{ $message->skipped_count }} skipped</span>@endif
                            </div>
                        </td>
                        <td class="px-5 py-4 text-xs text-slate-500">{{ $message->created_at->format('M d, Y H:i') }}</td>
                        <td class="px-5 py-4"><a href="{{ route('events.messages.show', [$event, $message]) }}" class="text-xs font-bold text-blue-700">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">No custom messages sent yet.</td></tr>
                @endforelse
            </tbody>
        </table></div></div>

        @if($messages->hasPages())<div class="mt-6">{{ $messages->links() }}</div>@endif
    </div>
</x-app-layout>
