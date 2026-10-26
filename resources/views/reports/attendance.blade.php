<x-app-layout>
    <x-slot name="header">Reports</x-slot>

    @php
        $query = fn (array $extra = []) => array_filter(['event' => $event->id, 'day' => $selectedDay, 'session' => $selectedSession] + $extra + $filters, fn ($value) => $value !== null && $value !== '');
    @endphp

    <div class="mx-auto max-w-7xl space-y-6 py-8 sm:px-6 lg:px-8">
        {{-- Title, period and downloads --}}
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-blue-600">Attendance report</p>
                    <h1 class="mt-1 text-2xl font-black text-slate-950">{{ $event->title }}</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $event->event_date->format('j M Y') }}@if($event->end_date && ! $event->end_date->isSameDay($event->event_date)) – {{ $event->end_date->format('j M Y') }}@endif
                        · {{ $event->totalDays() }} {{ \Illuminate\Support\Str::plural('day', $event->totalDays()) }}
                        · check-ins on {{ $grid['heldDays']->count() }}
                    </p>
                </div>
                <div data-tour="event-reports-export" class="flex flex-wrap gap-2 print:hidden">
                    <a href="{{ route('reports.excel', $query()) }}" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-extrabold text-white hover:bg-emerald-700">Download Excel</a>
                    <a href="{{ route('reports.pdf', $query()) }}" class="rounded-xl bg-red-600 px-4 py-2.5 text-xs font-extrabold text-white hover:bg-red-700">Download PDF</a>
                    <a href="{{ route('reports.csv', $query()) }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-extrabold text-slate-700 hover:bg-slate-50">CSV</a>
                </div>
            </div>
            <div class="mt-5">
                <x-day-selector :event="$event" :selectedDay="$selectedDay" :selectedSession="$selectedSession" :view="$view" :heldDays="$grid['heldDays']" />
            </div>
            @if($filters !== [])
                <p class="mt-3 text-xs font-semibold text-blue-700">Filtered: {{ \App\Services\AttendanceGrid::filterLabel($filters) }}. Downloads use the same filter.</p>
            @endif
        </div>

        {{-- View tabs --}}
        @include('reports._tabs', ['active' => $view, 'day' => $selectedDay, 'session' => $selectedSession])

        @include('reports.views.'.$view)
    </div>
</x-app-layout>
