@php
    $composeOpen = request()->boolean('compose') || (old('_compose') && $errors->any());
    $listQuery = fn ($extra = []) => array_filter(array_merge(['event' => $event, 'filter' => $filter !== 'all' ? $filter : null, 'q' => $search !== '' ? $search : null], $extra), fn ($value) => $value !== null);
    $filters = [
        'all' => ['All messages', 'M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H6.911a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661z'],
        'email' => ['Email', 'M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75'],
        'sms' => ['SMS', 'M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z'],
        'failed' => ['Needs attention', 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z'],
        'today' => ['Sent today', 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z'],
    ];
    $selectedInList = $selected && $messages->getCollection()->contains('id', $selected->id);
@endphp
<x-app-layout>
    <x-slot name="header">Messages</x-slot>

    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8"
         x-data="messagesPage({{ $composeOpen ? 'true' : 'false' }})"
         x-effect="document.body.classList.toggle('overflow-hidden', composeOpen)"
         @keydown.escape.window="composeOpen = false"
         @close-compose.window="composeOpen = false">

        @if(session('success'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('success') }}</div>
        @endif
        @if($errors->any() && ! $composeOpen)
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-4 transition-[grid-template-columns] duration-200" :class="sidebar ? 'lg:grid-cols-[13rem_minmax(0,1fr)]' : 'lg:grid-cols-[3.5rem_minmax(0,1fr)]'">
            {{-- Sidebar (collapsible on large screens) --}}
            <aside class="min-w-0 lg:sticky lg:top-4 lg:self-start">
                <div class="mb-3 flex items-center gap-2" :class="sidebar ? '' : 'lg:flex-col'">
                    <button type="button" @click="composeOpen = true" title="Compose message"
                            class="flex items-center justify-center gap-2 rounded-2xl bg-blue-900 py-3 text-sm font-black text-white shadow-lg shadow-blue-900/20 transition hover:bg-blue-800"
                            :class="sidebar ? 'flex-1 px-4' : 'flex-1 px-4 lg:w-full lg:flex-none lg:px-0'">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        <span :class="sidebar ? '' : 'lg:hidden'">Compose</span>
                    </button>
                    <button type="button" @click="toggleSidebar()" :title="sidebar ? 'Collapse sidebar' : 'Expand sidebar'" :aria-expanded="sidebar.toString()" aria-label="Toggle sidebar"
                            class="hidden h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 lg:inline-flex">
                        <svg class="h-5 w-5 transition-transform" :class="sidebar ? '' : 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5"/></svg>
                    </button>
                </div>

                <nav class="flex gap-1 overflow-x-auto lg:flex-col" aria-label="Message filters">
                    @foreach($filters as $key => [$label, $iconPath])
                        <a href="{{ route('events.messages.index', array_filter(['event' => $event, 'filter' => $key !== 'all' ? $key : null])) }}"
                           title="{{ $label }}" @if($filter === $key) aria-current="page" @endif
                           class="relative flex shrink-0 items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-bold transition {{ $filter === $key ? 'bg-blue-100 text-blue-900' : 'text-slate-600 hover:bg-slate-100' }}"
                           :class="sidebar ? '' : 'lg:justify-center lg:px-0'">
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}"/></svg>
                            <span class="flex-1" :class="sidebar ? '' : 'lg:hidden'">{{ $label }}</span>
                            <span :class="sidebar ? '' : 'lg:hidden'" class="rounded-full px-2 py-0.5 text-[11px] font-black {{ $key === 'failed' && $counts[$key] > 0 ? 'bg-rose-100 text-rose-700' : 'bg-white text-slate-500' }}">{{ $counts[$key] }}</span>
                            @if($key === 'failed' && $counts[$key] > 0)<span x-show="!sidebar" x-cloak class="absolute right-2 top-1.5 hidden h-2 w-2 rounded-full bg-rose-500 lg:block"></span>@endif
                        </a>
                    @endforeach
                </nav>
            </aside>

            {{-- Messages: list and reading combined --}}
            <section class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex max-h-[calc(100vh-12rem)] min-h-[26rem] flex-col">
                    <form method="GET" action="{{ route('events.messages.index', $event) }}" data-live-search="#message-results" class="flex shrink-0 items-center gap-3 border-b border-slate-100 p-3">
                        @if($filter !== 'all')<input type="hidden" name="filter" value="{{ $filter }}">@endif
                        <div class="relative flex-1">
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
                            <input type="search" name="q" value="{{ $search }}" autocomplete="off" placeholder="Search messages" class="w-full rounded-xl border-slate-200 bg-slate-50 py-2 pl-9 text-sm focus:bg-white">
                        </div>
                        <span class="hidden shrink-0 text-xs font-bold text-slate-400 sm:block">{{ $messages->total() }} {{ \Illuminate\Support\Str::plural('message', $messages->total()) }}</span>
                    </form>

                    <div id="message-results" class="min-h-0 flex-1 overflow-y-auto">
                        @if($selected && ! $selectedInList)
                            <div class="border-b border-blue-100 bg-blue-50/60">
                                <div class="flex items-center justify-between gap-3 px-4 py-3">
                                    <p class="truncate text-sm font-black text-slate-900">{{ $selected->subject ?: ($selected->email_body ? '(no subject)' : 'Text message') }}</p>
                                    <a href="{{ route('events.messages.index', $listQuery()) }}" class="shrink-0 text-xs font-bold text-slate-500 underline">Close</a>
                                </div>
                                @include('events.messages._detail')
                            </div>
                        @endif

                        <ul class="divide-y divide-slate-100">
                            @forelse($messages as $item)
                                @php($isOpen = $selected && $selected->id === $item->id)
                                @php($itemTotal = max(1, $item->sent_count + $item->failed_count + $item->skipped_count + $item->pending_count))
                                <li id="message-{{ $item->id }}">
                                    <a href="{{ $isOpen ? route('events.messages.index', $listQuery()) : route('events.messages.index', $listQuery(['message' => $item->id])) }}"
                                       aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                                       class="block px-4 py-3 transition {{ $isOpen ? 'bg-blue-50/70' : 'hover:bg-slate-50' }}">
                                        <div class="flex items-start gap-3">
                                            <svg class="mt-1 h-4 w-4 shrink-0 text-slate-400 transition-transform {{ $isOpen ? 'rotate-90' : '' }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/></svg>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-baseline justify-between gap-3">
                                                    <p class="truncate text-sm font-black text-slate-900">{{ $item->subject ?: ($item->email_body ? '(no subject)' : 'Text message') }}</p>
                                                    <span class="shrink-0 text-[11px] text-slate-400">{{ $item->created_at->diffForHumans(null, true, true) }}</span>
                                                </div>
                                                <p class="mt-0.5 truncate text-xs text-slate-500">{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $item->email_body ?: $item->sms_body), 140) }}</p>
                                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                                    @if($item->email_body)<span class="rounded bg-blue-100 px-1.5 py-0.5 text-[9px] font-black uppercase text-blue-700">Email</span>@endif
                                                    @if($item->sms_body)<span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[9px] font-black uppercase text-emerald-700">SMS</span>@endif
                                                    <span class="text-[11px] text-slate-400">{{ $item->recipient_count }} {{ \Illuminate\Support\Str::plural('person', $item->recipient_count) }}</span>
                                                    @if($item->failed_count)<span class="text-[11px] font-bold text-rose-600">{{ $item->failed_count }} failed</span>@endif
                                                    @if($item->pending_count)<span class="text-[11px] font-bold text-amber-600">sending…</span>@endif
                                                    <span class="ml-auto flex h-1 w-24 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                                                        <span class="bg-emerald-500" style="width: {{ $item->sent_count / $itemTotal * 100 }}%"></span>
                                                        <span class="bg-rose-500" style="width: {{ $item->failed_count / $itemTotal * 100 }}%"></span>
                                                        <span class="bg-amber-400" style="width: {{ $item->pending_count / $itemTotal * 100 }}%"></span>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                    @if($isOpen)
                                        @include('events.messages._detail')
                                    @endif
                                </li>
                            @empty
                                <li class="px-4 py-16 text-center text-sm text-slate-400">
                                    {{ $search !== '' || $filter !== 'all' ? 'No messages match.' : 'No messages yet. Write your first message to a group of people, as email, SMS or both.' }}
                                    @if($search === '' && $filter === 'all')<button type="button" @click="composeOpen = true" class="mx-auto mt-4 block rounded-xl bg-blue-900 px-5 py-2.5 text-xs font-black uppercase tracking-widest text-white hover:bg-blue-800">Compose message</button>@endif
                                </li>
                            @endforelse
                        </ul>

                        @if($messages->hasPages())<div class="border-t border-slate-100 p-3">{{ $messages->links() }}</div>@endif
                    </div>
                </div>
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
        // The sidebar's open/closed choice is remembered in the browser; storage can be
        // blocked, in which case it simply starts open each time.
        window.messagesPage = function (composeOpen) {
            return {
                composeOpen: composeOpen,
                sidebar: (function () {
                    try { return window.localStorage.getItem('messagesSidebar') !== 'closed'; } catch (error) { return true; }
                })(),
                toggleSidebar() {
                    this.sidebar = !this.sidebar;
                    try { window.localStorage.setItem('messagesSidebar', this.sidebar ? 'open' : 'closed'); } catch (error) { /* not persisted */ }
                },
            };
        };

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
