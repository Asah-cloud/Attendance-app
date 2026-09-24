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

        @php($statusColors = ['sent' => 'bg-emerald-100 text-emerald-700', 'pending' => 'bg-amber-100 text-amber-700', 'failed' => 'bg-rose-100 text-rose-700', 'skipped' => 'bg-slate-100 text-slate-600'])
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-black text-slate-900">Recipients <span class="ml-1 rounded-full bg-blue-50 px-2.5 py-1 text-xs text-blue-700">{{ $recipients->count() }}</span></h2>
            <p class="text-xs text-slate-400">One card per person</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            @forelse($recipients as $channels)
                @php($person = $channels->first())
                <article x-data="{ open: true }" x-show="open" x-transition class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-start justify-between gap-3 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-5 py-4">
                        <div class="min-w-0">
                            <h3 class="truncate font-black text-slate-900">{{ $person->name }}</h3>
                            <p class="mt-1 break-all text-xs text-slate-500">{{ $person->email ?: '—' }}</p>
                            @if($person->phone)<p class="mt-0.5 text-xs text-slate-500">{{ $person->phone }}</p>@endif
                        </div>
                        <button type="button" @click="open = false" aria-label="Close {{ $person->name }} card" class="rounded-full p-1.5 text-slate-400 transition hover:bg-slate-200 hover:text-slate-700">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        </button>
                    </div>
                    <div class="space-y-2 p-4">
                        @foreach($channels as $recipient)
                            <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-3 py-2.5">
                                <span class="text-xs font-bold text-slate-600">{{ $recipient->channel === 'sms' ? 'SMS' : ($recipient->channel === 'mail' ? 'Email' : 'No channel') }}</span>
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide {{ $statusColors[$recipient->status] ?? 'bg-gray-100 text-gray-600' }}">{{ $recipient->status }}</span>
                            </div>
                            @if($recipient->error_message)<p class="px-1 text-xs text-rose-600">{{ $recipient->error_message }}</p>@endif
                        @endforeach
                    </div>
                </article>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center text-sm text-slate-500">No recipients.</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
