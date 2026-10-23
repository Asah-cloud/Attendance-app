@php use App\Support\AccommodationPriority as Priority; @endphp
<x-app-layout>
    <x-slot name="header">Who needs a room</x-slot>
    <div class="py-10"><div class="mx-auto max-w-7xl space-y-7 sm:px-6 lg:px-8">
        @include('accommodation.sections._header', ['title' => 'Who needs a room', 'intro' => 'Mark who needs a bed and place people one at a time.'])
        @if($errors->any())<div class="rounded-2xl bg-red-50 p-4 text-sm font-bold text-red-800">{{ $errors->first() }}</div>@endif
        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <details data-acc="attendees" open class="group">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 border-b border-slate-100 p-6">
                    <span>
                        <span class="block text-xl font-black">Who needs a room</span>
                        <span class="mt-1 block text-sm text-slate-500">Only you can see this until you tick "Show rooms to attendees".</span>
                    </span>
                    <svg class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="p-4">
                    <livewire:room-assignments :event="$event" :key="'room-assignments-'.$event->id" />
                </div>
            </details>
        </section>
    </div></div>
    {{-- Keep expanded panels and scroll position across the form round-trips on this page. --}}
    <script>
        (() => {
            const OPEN = 'acc-open:{{ $event->id }}';
            const SCROLL = 'acc-scroll:{{ $event->id }}';
            const read = () => { try { return new Set(JSON.parse(localStorage.getItem(OPEN) || '[]')); } catch (e) { return new Set(); } };
            const write = (s) => { try { localStorage.setItem(OPEN, JSON.stringify([...s])); } catch (e) {} };

            const remembered = read();
            document.querySelectorAll('details[data-acc]').forEach((d) => {
                if (remembered.has(d.dataset.acc)) d.open = true;
                d.addEventListener('toggle', () => {
                    const s = read();
                    if (d.open) s.add(d.dataset.acc); else s.delete(d.dataset.acc);
                    write(s);
                });
            });

            try {
                if (history.scrollRestoration) history.scrollRestoration = 'manual';
                const y = sessionStorage.getItem(SCROLL);
                if (y !== null) { window.scrollTo(0, parseInt(y, 10) || 0); sessionStorage.removeItem(SCROLL); }
            } catch (e) {}
            document.querySelectorAll('form').forEach((f) => f.addEventListener('submit', () => {
                try { sessionStorage.setItem(SCROLL, String(window.scrollY)); } catch (e) {}
            }));
        })();
    </script>
</x-app-layout>
