@php
    $statusColors = ['sent' => 'bg-emerald-100 text-emerald-700', 'pending' => 'bg-amber-100 text-amber-700', 'failed' => 'bg-rose-100 text-rose-700', 'skipped' => 'bg-slate-100 text-slate-600'];
    $composeOpen = request()->boolean('compose') || (old('_compose') && $errors->any());
    $listQuery = fn ($extra = []) => array_filter(array_merge(['event' => $event, 'filter' => $filter !== 'all' ? $filter : null, 'q' => $search !== '' ? $search : null], $extra), fn ($value) => $value !== null);
    $filters = ['all' => 'All messages', 'email' => 'Email', 'sms' => 'SMS', 'failed' => 'Needs attention', 'today' => 'Sent today'];
    $total = $reading ? $reading->sent_count + $reading->failed_count + $reading->skipped_count + $reading->pending_count : 0;
@endphp
<x-app-layout>
    <x-slot name="header">Messages</x-slot>

    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8"
         x-data="{ composeOpen: {{ $composeOpen ? 'true' : 'false' }} }"
         x-effect="document.body.classList.toggle('overflow-hidden', composeOpen)"
         @keydown.escape.window="composeOpen = false"
         @close-compose.window="composeOpen = false">

        @if(session('success'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('success') }}</div>
        @endif
        @if($errors->any() && ! $composeOpen)
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-4 lg:grid-cols-[13rem_minmax(18rem,22rem)_minmax(0,1fr)]">
            {{-- Sidebar --}}
            <aside class="{{ $hasSelection ? 'hidden lg:block' : '' }}">
                <button type="button" @click="composeOpen = true" class="mb-3 flex w-full items-center justify-center gap-2 rounded-2xl bg-blue-900 px-4 py-3 text-sm font-black text-white shadow-lg shadow-blue-900/20 transition hover:bg-blue-800">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"/></svg>
                    Compose
                </button>
                <nav class="flex gap-1 overflow-x-auto lg:flex-col" aria-label="Message filters">
                    @foreach($filters as $key => $label)
                        <a href="{{ route('events.messages.index', array_filter(['event' => $event, 'filter' => $key !== 'all' ? $key : null, 'q' => $search !== '' ? $search : null])) }}"
                           @if($filter === $key) aria-current="page" @endif
                           class="flex shrink-0 items-center justify-between gap-3 rounded-xl px-3 py-2 text-sm font-bold transition {{ $filter === $key ? 'bg-blue-100 text-blue-900' : 'text-slate-600 hover:bg-slate-100' }}">
                            <span>{{ $label }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-black {{ $key === 'failed' && $counts[$key] > 0 ? 'bg-rose-100 text-rose-700' : 'bg-white text-slate-500' }}">{{ $counts[$key] }}</span>
                        </a>
                    @endforeach
                </nav>
            </aside>

            {{-- Message list --}}
            <section class="{{ $hasSelection ? 'hidden lg:block' : '' }} overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <form method="GET" action="{{ route('events.messages.index', $event) }}" class="border-b border-slate-100 p-3">
                    @if($filter !== 'all')<input type="hidden" name="filter" value="{{ $filter }}">@endif
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
                        <input type="search" name="q" value="{{ $search }}" placeholder="Search messages" class="w-full rounded-xl border-slate-200 bg-slate-50 py-2 pl-9 text-sm focus:bg-white">
                    </div>
                </form>

                <ul class="max-h-[70vh] divide-y divide-slate-100 overflow-y-auto">
                    @forelse($messages as $item)
                        @php($itemTotal = max(1, $item->sent_count + $item->failed_count + $item->skipped_count + $item->pending_count))
                        <li>
                            <a href="{{ route('events.messages.index', $listQuery(['message' => $item->id])) }}"
                               class="block px-4 py-3 transition {{ $reading && $reading->id === $item->id ? 'bg-blue-50/70' : 'hover:bg-slate-50' }}">
                                <div class="flex items-baseline justify-between gap-3">
                                    <p class="truncate text-sm font-black text-slate-900">{{ $item->subject ?: ($item->email_body ? '(no subject)' : 'Text message') }}</p>
                                    <span class="shrink-0 text-[11px] text-slate-400">{{ $item->created_at->diffForHumans(null, true, true) }}</span>
                                </div>
                                <p class="mt-0.5 truncate text-xs text-slate-500">{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $item->email_body ?: $item->sms_body), 90) }}</p>
                                <div class="mt-2 flex items-center gap-2">
                                    @if($item->email_body)<span class="rounded bg-blue-100 px-1.5 py-0.5 text-[9px] font-black uppercase text-blue-700">Email</span>@endif
                                    @if($item->sms_body)<span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[9px] font-black uppercase text-emerald-700">SMS</span>@endif
                                    <span class="text-[11px] text-slate-400">{{ $item->recipient_count }} {{ \Illuminate\Support\Str::plural('person', $item->recipient_count) }}</span>
                                    @if($item->failed_count)<span class="text-[11px] font-bold text-rose-600">{{ $item->failed_count }} failed</span>@endif
                                    @if($item->pending_count)<span class="text-[11px] font-bold text-amber-600">sending…</span>@endif
                                </div>
                                <div class="mt-2 flex h-1 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                                    <span class="bg-emerald-500" style="width: {{ $item->sent_count / $itemTotal * 100 }}%"></span>
                                    <span class="bg-rose-500" style="width: {{ $item->failed_count / $itemTotal * 100 }}%"></span>
                                    <span class="bg-amber-400" style="width: {{ $item->pending_count / $itemTotal * 100 }}%"></span>
                                </div>
                            </a>
                        </li>
                    @empty
                        <li class="px-4 py-14 text-center text-sm text-slate-400">
                            {{ $search !== '' || $filter !== 'all' ? 'No messages match.' : 'No messages yet.' }}
                            @if($search === '' && $filter === 'all')<button type="button" @click="composeOpen = true" class="mt-3 block w-full text-xs font-black text-blue-700">Compose your first message</button>@endif
                        </li>
                    @endforelse
                </ul>

                @if($messages->hasPages())<div class="border-t border-slate-100 p-3">{{ $messages->links() }}</div>@endif
            </section>

            {{-- Reading pane --}}
            <section class="{{ $hasSelection ? '' : 'hidden lg:block' }} min-w-0">
                @if($reading)
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 px-5 py-4">
                            <a href="{{ route('events.messages.index', $listQuery()) }}" class="mb-2 inline-block text-xs font-bold text-slate-500 lg:hidden">&larr; All messages</a>
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h1 class="break-words text-xl font-black text-slate-900">{{ $reading->subject ?: ($reading->email_body ? '(no subject)' : 'Text message') }}</h1>
                                    <p class="mt-1 text-xs text-slate-400">{{ $reading->creator?->name ?? 'A manager' }} · {{ $reading->created_at->format('M d, Y H:i') }} · {{ str_replace('_', ' ', $reading->mode) }}</p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    @if($reading->failed_count)
                                        <form method="POST" action="{{ route('events.messages.retry', [$event, $reading]) }}">@csrf<button class="rounded-xl bg-rose-600 px-3.5 py-2 text-xs font-black uppercase tracking-wide text-white hover:bg-rose-700">Retry {{ $reading->failed_count }} failed</button></form>
                                    @endif
                                    <a href="{{ route('events.messages.edit', [$event, $reading]) }}" class="rounded-xl border border-slate-200 px-3.5 py-2 text-xs font-black uppercase tracking-wide text-slate-700 hover:bg-slate-50">Edit &amp; resend</a>
                                </div>
                            </div>
                        </div>

                        {{-- Delivery --}}
                        <div class="border-b border-slate-100 bg-slate-50/60 px-5 py-4"
                             x-data="messageProgress(@js(route('events.messages.progress', [$event, $reading])), @js(['sent' => $reading->sent_count, 'failed' => $reading->failed_count, 'skipped' => $reading->skipped_count, 'pending' => $reading->pending_count]))"
                             x-init="start()">
                            <div class="mb-2 flex items-center justify-between text-xs">
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
                        <div class="grid gap-4 p-5 md:grid-cols-2">
                            @if($reading->email_body)
                                <div class="rounded-2xl border border-slate-200 p-4">
                                    <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">Email</p>
                                    <p class="mt-2 whitespace-pre-line break-words text-sm text-slate-700">{{ $reading->email_body }}</p>
                                    @if(!empty($reading->attachments))
                                        <div class="mt-3 flex flex-wrap gap-1.5">@foreach($reading->attachments as $attachment)<span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600">{{ $attachment['name'] }}</span>@endforeach</div>
                                    @endif
                                </div>
                            @endif
                            @if($reading->sms_body)
                                <div class="rounded-2xl border border-slate-200 p-4">
                                    <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">SMS</p>
                                    <div class="mt-2 inline-block max-w-full rounded-2xl rounded-bl-sm bg-blue-600 px-3.5 py-2 text-sm text-white"><p class="whitespace-pre-line break-words">{{ $reading->sms_body }}</p></div>
                                </div>
                            @endif
                        </div>

                        {{-- Recipients --}}
                        <div class="border-t border-slate-100 p-5" x-data="{ filter: 'all' }">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                                <h2 class="text-sm font-black text-slate-900">Recipients <span class="ml-1 rounded-full bg-blue-50 px-2 py-0.5 text-xs text-blue-700">{{ $recipientGroups->count() }}</span></h2>
                                <div class="flex flex-wrap gap-1">
                                    @foreach(['all' => 'All', 'sent' => 'Sent', 'failed' => 'Failed', 'skipped' => 'Skipped', 'pending' => 'Pending'] as $key => $label)
                                        <button type="button" @click="filter = '{{ $key }}'" class="rounded-full px-3 py-1 text-[11px] font-bold transition" :class="filter === '{{ $key }}' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">{{ $label }}</button>
                                    @endforeach
                                </div>
                            </div>

                            <form method="POST" action="{{ route('events.messages.resend', [$event, $reading]) }}" class="space-y-3">
                                @csrf
                                <input type="hidden" name="subject" value="{{ $reading->subject }}"><input type="hidden" name="email_body" value="{{ $reading->email_body }}"><input type="hidden" name="sms_body" value="{{ $reading->sms_body }}"><input type="hidden" name="mode" value="{{ $reading->mode }}">
                                <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2">
                                    <p class="text-xs text-slate-500">Tick people to send this message to them again.</p>
                                    <button type="submit" class="rounded-lg bg-blue-900 px-3.5 py-1.5 text-[11px] font-black uppercase tracking-wide text-white hover:bg-blue-800">Resend selected</button>
                                </div>

                                <div class="grid max-h-[32rem] gap-2 overflow-y-auto sm:grid-cols-2">
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
                                        <p class="col-span-full rounded-xl border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-400">No recipients.</p>
                                    @endforelse
                                </div>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="flex min-h-[24rem] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                        <p class="text-base font-black text-slate-800">Nothing to read yet</p>
                        <p class="mt-1 max-w-xs text-sm text-slate-500">Write your first message to a group of people. You can send it as email, SMS, or both.</p>
                        <button type="button" @click="composeOpen = true" class="mt-4 rounded-xl bg-blue-900 px-5 py-2.5 text-xs font-black uppercase tracking-widest text-white hover:bg-blue-800">Compose message</button>
                    </div>
                @endif
            </section>
        </div>

        {{-- Compose panel --}}
        <div x-show="composeOpen" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-stretch justify-center sm:items-center sm:p-6" role="dialog" aria-modal="true" aria-label="Compose message">
            <div class="absolute inset-0 bg-slate-900/60" @click="composeOpen = false"></div>
            <div class="relative flex h-full w-full max-w-6xl flex-col sm:h-auto sm:max-h-[92vh]">
                @include('events.messages._compose', ['inModal' => true])
            </div>
        </div>
    </div>

    @verbatim
    <script>
        window.messageProgress = function (url, initial) {
            return {
                counts: initial,
                timer: null,
                wasPending: initial.pending > 0,
                get total() { return this.counts.sent + this.counts.failed + this.counts.skipped + this.counts.pending; },
                get done() { return this.counts.sent + this.counts.failed + this.counts.skipped; },
                pct(key) { return this.total ? (this.counts[key] / this.total * 100).toFixed(1) : 0; },
                start() {
                    if (this.counts.pending > 0) this.timer = setInterval(() => this.poll(), 3000);
                },
                async poll() {
                    try {
                        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        if (!response.ok) return;
                        this.counts = await response.json();
                        if (this.counts.pending === 0) {
                            clearInterval(this.timer);
                            if (this.wasPending) window.location.reload();
                        }
                    } catch (error) { /* try again on the next tick */ }
                },
            };
        };
    </script>
    @endverbatim
</x-app-layout>
