{{-- Which part of the event a report covers: all days, Arrival, or one day (and session). --}}
@props(['event', 'selectedDay', 'selectedSession' => null, 'view' => 'simple', 'heldDays' => collect()])

@php
    $link = fn ($day, $session = null) => route('reports.event', array_filter(['event' => $event->id, 'day' => $day, 'session' => $session, 'view' => $view === 'simple' ? null : $view], fn ($value) => $value !== null));
    $pill = fn (bool $active) => $active
        ? 'bg-slate-900 text-white shadow-sm'
        : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50';
    $sessions = is_numeric($selectedDay) && (int) $selectedDay >= 1 ? $event->sessionsForDay((int) $selectedDay) : collect();
@endphp

<div class="print:hidden">
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ $link('all') }}" class="rounded-full px-4 py-2 text-xs font-black transition {{ $pill($selectedDay === 'all') }}">All days</a>
        @if($event->has_arrival_session)
            <a href="{{ $link(0) }}" class="rounded-full px-4 py-2 text-xs font-black transition {{ $pill((string) $selectedDay === '0') }}">Arrival</a>
        @endif
        @for($i = 1; $i <= $event->totalDays(); $i++)
            @php($wasHeld = $heldDays->contains($i))
            <a href="{{ $link($i) }}" title="{{ $wasHeld ? 'Day '.$i : 'No check-ins recorded on Day '.$i }}"
               class="rounded-full px-4 py-2 text-xs font-black transition {{ $pill((string) $selectedDay === (string) $i) }} {{ $wasHeld || (string) $selectedDay === (string) $i ? '' : 'opacity-50' }}">
                Day {{ $i }}
            </a>
        @endfor
    </div>
    @if($sessions->count() > 1)
        <div class="mt-2 flex flex-wrap items-center gap-2 pl-1">
            <span class="text-[11px] font-black uppercase tracking-wider text-slate-400">Session</span>
            <a href="{{ $link($selectedDay) }}" class="rounded-full px-3 py-1 text-[11px] font-bold {{ $pill(! $selectedSession) }}">All sessions</a>
            @foreach($sessions as $s)
                <a href="{{ $link($selectedDay, $s['session']) }}" class="rounded-full px-3 py-1 text-[11px] font-bold {{ $pill((int) $selectedSession === $s['session']) }}">{{ $s['label'] ?: 'Session '.$s['session'] }}</a>
            @endforeach
        </div>
    @endif
    @if($heldDays->count() < $event->totalDays() && $event->status !== 'upcoming')
        <p class="mt-2 text-[11px] font-semibold text-slate-400">Faded days have no check-ins and are left out of averages and "days attended".</p>
    @endif
</div>
