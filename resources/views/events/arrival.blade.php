<x-app-layout>
    <x-slot name="header">Arrival</x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-8 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-3xl bg-gradient-to-br from-cyan-700 to-blue-900 p-7 text-white shadow-xl sm:p-9">
                <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-center">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.2em] text-cyan-200">Arrival workspace</p>
                        <h1 class="mt-2 text-3xl font-black">{{ $event->title }}</h1>
                        <p class="mt-3 text-sm text-cyan-100">Check members in, issue tags and essentials, then make them available for daily attendance.</p>
                        <p class="mt-2 text-xs font-bold text-cyan-200">Arrival date: {{ $event->arrival_date?->format('M j, Y') }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @can('scanAttendance', $event)
                            <a href="{{ route('events.arrival.scanner', $event) }}" class="rounded-xl bg-white px-5 py-3 text-sm font-black text-blue-900">Open Arrival scanner</a>
                            <a href="{{ URL::signedRoute('arrival.public', ['event' => $event->slug]) }}" target="_blank" class="rounded-xl bg-cyan-200 px-5 py-3 text-sm font-black text-cyan-950">Phone check-in page</a>
                        @endcan
                        <a href="{{ route('reports.event', ['event' => $event, 'day' => 0]) }}" class="rounded-xl border border-white/30 px-5 py-3 text-sm font-black text-white">Arrival report</a>
                    </div>
                </div>
            </section>

            <div class="grid gap-4 sm:grid-cols-3">
                <x-summary-card title="Confirmed" :value="$confirmedCount" color="blue" />
                <x-summary-card title="Arrived" :value="$arrivedCount" color="green" />
                <x-summary-card title="Yet to arrive" :value="max(0, $confirmedCount - $arrivedCount)" color="red" />
            </div>

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 bg-slate-50/70 p-6">
                    <h2 class="text-xs font-black uppercase tracking-widest text-slate-700">Confirmed arrival list</h2>
                    <p class="mt-2 text-sm text-slate-500">Everyone remains here until checked in. Arrived members then become available on the Attendance page.</p>
                </div>
                <div class="p-6">
                    <livewire:attendance-search :event="$event" :day="0" mode="arrival" :key="'arrival-search-'.$event->id" />
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
