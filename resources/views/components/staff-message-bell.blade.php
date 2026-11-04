{{--
    Staff's message bell: messages from admins and managers (read only, no replies). Opening the bell
    marks them read; an urgent one covers the top of the screen until "Got it". Polls
    StaffMessageController@feed while the tab is visible.
--}}
<div x-data="{
        open: false,
        unread: 0,
        items: [],
        urgent: [],
        known: null,
        init() {
            this.load();
            setInterval(() => { if (! document.hidden) this.load() }, 20000);
            document.addEventListener('visibilitychange', () => { if (! document.hidden) this.load() });
        },
        async load() {
            try {
                const response = await fetch(@js(route('staff-messages.feed')), { headers: { Accept: 'application/json' } });
                if (! response.ok) return;
                const data = await response.json();
                const fresh = this.known === null ? [] : data.items.filter(item => item.unread && ! this.known.includes(item.id));
                this.known = data.items.map(item => item.id);
                this.unread = data.unread;
                this.items = data.items;
                this.urgent = data.urgent;
                if (fresh.some(item => item.urgent) && navigator.vibrate) navigator.vibrate([200, 100, 200]);
                fresh.filter(item => ! item.urgent).forEach(item => window.dispatchEvent(new CustomEvent('notify', { detail: { message: 'New message from ' + item.from + ': ' + item.body.slice(0, 80), type: 'warning' } })));
            } catch (e) {}
        },
        async markRead(ids = null) {
            try {
                const response = await fetch(@js(route('staff-messages.read')), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify(ids === null ? {} : { ids }),
                });
                if (response.ok) this.unread = (await response.json()).unread;
            } catch (e) {}
        },
        toggle() {
            this.open = ! this.open;
            if (this.open && this.unread) {
                this.markRead();
                this.urgent = [];
                setTimeout(() => this.items = this.items.map(item => ({ ...item, unread: false })), 1500);
            }
        },
        gotIt(item) {
            this.urgent = this.urgent.filter(other => other.id !== item.id);
            this.items = this.items.map(other => other.id === item.id ? { ...other, unread: false } : other);
            this.markRead([item.id]);
        },
     }"
     @click.outside="open = false" @keydown.escape.window="open = false" class="relative">
    <button type="button" @click="toggle()" class="relative grid h-10 w-10 place-items-center rounded-xl border border-slate-200 text-slate-600 transition hover:border-blue-300 hover:text-blue-700" aria-label="Messages" :aria-expanded="open.toString()">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-4-5.7V5a2 2 0 1 0-4 0v.3A6 6 0 0 0 6 11v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 1 1-6 0m6 0H9"/></svg>
        <span x-cloak x-show="unread > 0" x-text="unread > 99 ? '99+' : unread" class="absolute -right-1.5 -top-1.5 min-w-[1.25rem] rounded-full bg-red-600 px-1 text-center text-[10px] font-black leading-5 text-white"></span>
    </button>

    <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 z-50 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
        <div class="border-b border-slate-100 px-4 py-3">
            <p class="text-sm font-black text-slate-900">Messages</p>
            <p class="text-xs text-slate-500">From your event coordinators</p>
        </div>
        <div class="max-h-[28rem] divide-y divide-slate-50 overflow-y-auto">
            <template x-for="item in items" :key="item.id">
                <div class="px-4 py-3" :class="item.urgent ? 'bg-amber-50' : (item.unread ? 'bg-blue-50/60' : '')">
                    <div class="flex items-center gap-2 text-xs font-bold text-slate-500">
                        <span x-show="item.urgent" class="rounded-full bg-amber-200 px-2 py-0.5 text-[10px] uppercase text-amber-900">Urgent</span>
                        <span x-text="item.from"></span>
                        <span class="font-normal text-slate-400" x-text="'· ' + item.ago"></span>
                    </div>
                    <p class="mt-1 whitespace-pre-line text-sm text-slate-800" x-text="item.body"></p>
                </div>
            </template>
            <p x-show="! items.length" class="px-4 py-6 text-center text-sm text-slate-400">No messages yet.</p>
        </div>
    </div>

    <template x-if="urgent.length">
        <div class="fixed inset-x-0 top-0 z-[95] p-3 sm:p-4" role="alertdialog" aria-live="assertive">
            <div class="mx-auto max-w-2xl rounded-2xl bg-amber-500 p-5 text-amber-950 shadow-2xl ring-4 ring-amber-300/60">
                <p class="text-xs font-black uppercase tracking-[0.2em]">Urgent message · <span x-text="urgent[0].from"></span> · <span x-text="urgent[0].time"></span></p>
                <p class="mt-2 whitespace-pre-line text-lg font-bold leading-snug" x-text="urgent[0].body"></p>
                <div class="mt-4 flex items-center justify-between gap-3">
                    <span class="text-xs font-bold" x-show="urgent.length > 1" x-text="(urgent.length - 1) + ' more urgent'"></span>
                    <button type="button" @click="gotIt(urgent[0])" class="ml-auto rounded-xl bg-amber-950 px-6 py-3 text-sm font-black text-white">Got it</button>
                </div>
            </div>
        </div>
    </template>
</div>
