@php
    $statusColors = ['sent' => 'bg-emerald-100 text-emerald-700', 'pending' => 'bg-amber-100 text-amber-700', 'failed' => 'bg-rose-100 text-rose-700', 'skipped' => 'bg-slate-100 text-slate-600'];
@endphp
<div id="message-detail" x-init="$el.scrollIntoView({ block: 'nearest' })" class="border-t border-blue-100 bg-slate-50/50">
    <div class="flex flex-wrap items-start justify-between gap-3 px-5 pt-4">
        <p class="text-xs text-slate-400">{{ $selected->creator?->name ?? 'A manager' }} · {{ $selected->created_at->format('M d, Y H:i') }} · {{ str_replace('_', ' ', $selected->mode) }}</p>
        <div class="flex flex-wrap items-center gap-2">
            @if($selected->failed_count)
                <form method="POST" action="{{ route('events.messages.retry', [$event, $selected]) }}">@csrf<button class="rounded-xl bg-rose-600 px-3.5 py-2 text-xs font-black uppercase tracking-wide text-white hover:bg-rose-700">Retry {{ $selected->failed_count }} failed</button></form>
            @endif
            <a href="{{ route('events.messages.edit', [$event, $selected]) }}" class="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-black uppercase tracking-wide text-slate-700 hover:bg-slate-50">Edit &amp; resend</a>
        </div>
    </div>

    {{-- Delivery --}}
    <div class="px-5 pt-4"
         x-data="messageProgress(@js(route('events.messages.progress', [$event, $selected])), @js(['sent' => $selected->sent_count, 'failed' => $selected->failed_count, 'skipped' => $selected->skipped_count, 'pending' => $selected->pending_count]))"
         x-init="start()">
        <div class="mb-1.5 flex items-center justify-between text-xs">
            <span class="font-black uppercase tracking-widest text-slate-400" x-text="counts.pending > 0 ? 'Sending… ' + done + ' of ' + total + ' done' : 'Delivery'"></span>
            <span class="inline-flex items-center gap-1.5 font-bold text-amber-600" x-show="counts.pending > 0" x-cloak><span class="h-2 w-2 animate-pulse rounded-full bg-amber-500"></span>live</span>
        </div>
        <div class="flex h-2.5 overflow-hidden rounded-full bg-slate-200" role="progressbar" :aria-valuenow="done" aria-valuemin="0" :aria-valuemax="total">
            <span class="bg-emerald-500 transition-all duration-500" :style="'width:' + pct('sent') + '%'"></span>
            <span class="bg-rose-500 transition-all duration-500" :style="'width:' + pct('failed') + '%'"></span>
            <span class="bg-slate-400 transition-all duration-500" :style="'width:' + pct('skipped') + '%'"></span>
            <span class="animate-pulse bg-amber-400 transition-all duration-500" :style="'width:' + pct('pending') + '%'"></span>
        </div>
        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs font-bold">
            <span class="text-emerald-700"><span x-text="counts.sent"></span> sent</span>
            <span class="text-rose-700"><span x-text="counts.failed"></span> failed</span>
            <span class="text-slate-500"><span x-text="counts.skipped"></span> skipped</span>
            <span class="text-amber-600"><span x-text="counts.pending"></span> pending</span>
        </div>
    </div>

    {{-- Content --}}
    <div class="grid gap-3 px-5 pt-4 md:grid-cols-2">
        @if($selected->email_body)
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">Email</p>
                <p class="mt-2 max-h-48 overflow-y-auto whitespace-pre-line break-words text-sm text-slate-700">{{ $selected->email_body }}</p>
                @if(!empty($selected->attachments))
                    <div class="mt-3 flex flex-wrap gap-1.5">@foreach($selected->attachments as $attachment)<span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600">{{ $attachment['name'] }}</span>@endforeach</div>
                @endif
            </div>
        @endif
        @if($selected->sms_body)
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">SMS</p>
                <div class="mt-2 inline-block max-h-48 max-w-full overflow-y-auto rounded-2xl rounded-bl-sm bg-blue-600 px-3.5 py-2 text-sm text-white"><p class="whitespace-pre-line break-words">{{ $selected->sms_body }}</p></div>
            </div>
        @endif
    </div>

    {{-- Recipients --}}
    <div class="p-5" x-data="{ filter: 'all' }">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-black text-slate-900">Recipients <span class="ml-1 rounded-full bg-blue-50 px-2 py-0.5 text-xs text-blue-700">{{ $recipientGroups->count() }}</span></h2>
            <div class="flex flex-wrap gap-1">
                @foreach(['all' => 'All', 'sent' => 'Sent', 'failed' => 'Failed', 'skipped' => 'Skipped', 'pending' => 'Pending'] as $key => $label)
                    <button type="button" @click="filter = '{{ $key }}'" class="rounded-full px-3 py-1 text-[11px] font-bold transition" :class="filter === '{{ $key }}' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-100'">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <form method="POST" action="{{ route('events.messages.resend', [$event, $selected]) }}" class="space-y-3">
            @csrf
            <input type="hidden" name="subject" value="{{ $selected->subject }}"><input type="hidden" name="email_body" value="{{ $selected->email_body }}"><input type="hidden" name="sms_body" value="{{ $selected->sms_body }}"><input type="hidden" name="mode" value="{{ $selected->mode }}">
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-white px-3 py-2 ring-1 ring-slate-200">
                <p class="text-xs text-slate-500">Tick people to send this message to them again.</p>
                <button type="submit" class="rounded-lg bg-blue-900 px-3.5 py-1.5 text-[11px] font-black uppercase tracking-wide text-white hover:bg-blue-800">Resend selected</button>
            </div>

            <div class="grid max-h-72 gap-2 overflow-y-auto sm:grid-cols-2 xl:grid-cols-3">
                @forelse($recipientGroups as $channels)
                    @php($person = $channels->first())
                    <article x-data="{ open: true }" x-show="open && (filter === 'all' || $el.dataset.status.split(' ').includes(filter))" data-status="{{ $channels->pluck('status')->unique()->implode(' ') }}" x-transition class="rounded-xl border border-slate-200 bg-white">
                        <div class="flex items-start justify-between gap-2 px-3 py-2.5">
                            <div class="min-w-0">
                                <h3 class="truncate text-sm font-black text-slate-900">{{ $person->name }}</h3>
                                <p class="truncate text-[11px] text-slate-500">{{ $person->email ?: '—' }}@if($person->phone) · {{ $person->phone }}@endif</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1.5">
                                @if($person->participant_id)
                                    <label class="flex items-center gap-1 text-[10px] font-bold text-blue-700"><input type="checkbox" name="participant_ids[]" value="{{ $person->participant_id }}" class="rounded border-slate-300"> Resend</label>
                                @else
                                    <label class="flex items-center gap-1 text-[10px] font-bold text-blue-700"><input type="checkbox" name="recipient_keys[]" value="{{ $person->id }}" class="rounded border-slate-300"> Resend</label>
                                @endif
                                <button type="button" @click="open = false" aria-label="Hide {{ $person->name }}" class="rounded-full p-1 text-slate-300 transition hover:bg-slate-100 hover:text-slate-600">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="space-y-1 border-t border-slate-100 px-3 py-2">
                            @foreach($channels as $recipient)
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-[11px] font-bold text-slate-600">{{ $recipient->channel === 'sms' ? 'SMS' : ($recipient->channel === 'mail' ? 'Email' : 'No channel') }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase tracking-wide {{ $statusColors[$recipient->status] ?? 'bg-gray-100 text-gray-600' }}">{{ $recipient->status }}</span>
                                </div>
                                @if($recipient->error_message)<p class="break-words text-[11px] text-rose-600">{{ $recipient->error_message }}</p>@endif
                            @endforeach
                        </div>
                    </article>
                @empty
                    <p class="col-span-full rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-400">No recipients.</p>
                @endforelse
            </div>
        </form>
    </div>
</div>
