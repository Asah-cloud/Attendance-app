{{--
    Platform admins' sign-in bell: unread count, the latest sign-ins and failed-attempt alerts, and
    a short pop-up for each new one. Polls SignInActivityController@feed while the tab is visible.
--}}
<div x-data="{
        open: false,
        unread: 0,
        items: [],
        lastId: 0,
        popups: [],
        muted: false,
        init() {
            try { this.muted = localStorage.getItem('signInPopupsMuted') === '1' } catch (e) {}
            this.load();
            setInterval(() => { if (! document.hidden) this.load() }, 30000);
        },
        async load() {
            try {
                const response = await fetch(@js(route('admin.sign-ins.feed')) + '?after=' + this.lastId, { headers: { Accept: 'application/json' } });
                if (! response.ok) return;
                const data = await response.json();
                this.unread = data.unread;
                this.items = data.items;
                this.lastId = data.last_id;
                if (! this.muted && data.new.length) this.pop(data.new);
            } catch (e) {}
        },
        pop(entries) {
            // A burst (e.g. staff arriving on an event morning) becomes one summary, not a stack.
            const shown = entries.length > 3
                ? [{ id: 'burst-' + Date.now(), alert: entries.some(e => e.alert), summary: entries.length + ' new sign-ins - open the bell for details', time: '' }]
                : entries;
            shown.forEach(entry => {
                const key = entry.id + '-' + Math.random();
                this.popups.push({ ...entry, key });
                setTimeout(() => { this.popups = this.popups.filter(p => p.key !== key) }, 6000);
            });
        },
        async toggle() {
            this.open = ! this.open;
            if (this.open && this.unread) {
                this.unread = 0;
                await fetch(@js(route('admin.sign-ins.seen')), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' } });
            }
        },
        toggleMute() {
            this.muted = ! this.muted;
            try { localStorage.setItem('signInPopupsMuted', this.muted ? '1' : '0') } catch (e) {}
        },
     }"
     @click.outside="open = false" @keydown.escape.window="open = false" class="relative">
    <button type="button" @click="toggle()" class="relative grid h-10 w-10 place-items-center rounded-xl border border-slate-200 text-slate-600 transition hover:border-blue-300 hover:text-blue-700" aria-label="Sign-in activity" :aria-expanded="open.toString()">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-4-5.7V5a2 2 0 1 0-4 0v.3A6 6 0 0 0 6 11v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 1 1-6 0m6 0H9"/></svg>
        <span x-cloak x-show="unread > 0" x-text="unread > 99 ? '99+' : unread" class="absolute -right-1.5 -top-1.5 min-w-[1.25rem] rounded-full bg-red-600 px-1 text-center text-[10px] font-black leading-5 text-white"></span>
    </button>

    <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 z-50 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <p class="text-sm font-black text-slate-900">Sign-ins</p>
            <button type="button" @click="toggleMute()" class="text-xs font-bold text-slate-500 hover:text-blue-700" x-text="muted ? 'Turn pop-ups on' : 'Mute pop-ups'"></button>
        </div>
        <div class="max-h-96 divide-y divide-slate-50 overflow-y-auto">
            <template x-for="item in items" :key="item.id">
                <div class="flex gap-3 px-4 py-3" :class="item.alert ? 'bg-red-50' : ''">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="item.alert ? 'bg-red-500' : 'bg-emerald-500'"></span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-800" x-text="item.summary"></p>
                        <p class="text-xs text-slate-400" x-text="item.time + ' · ' + item.ago"></p>
                    </div>
                </div>
            </template>
            <p x-show="! items.length" class="px-4 py-6 text-center text-sm text-slate-400">No sign-ins yet.</p>
        </div>
        <a href="{{ route('admin.sign-ins.index') }}" class="block border-t border-slate-100 px-4 py-3 text-center text-xs font-black uppercase tracking-wider text-blue-700 hover:bg-blue-50">View all sign-ins</a>
    </div>

    <div class="pointer-events-none fixed bottom-4 right-4 z-[90] flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2" aria-live="polite">
        <template x-for="popup in popups" :key="popup.key">
            <div x-transition class="pointer-events-auto flex gap-3 rounded-2xl border bg-white px-4 py-3 shadow-xl" :class="popup.alert ? 'border-red-200' : 'border-slate-200'">
                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="popup.alert ? 'bg-red-500' : 'bg-emerald-500'"></span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-800" x-text="popup.summary"></p>
                    <p x-show="popup.time" class="text-xs text-slate-400" x-text="popup.time"></p>
                </div>
            </div>
        </template>
    </div>
</div>
