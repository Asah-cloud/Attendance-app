{{-- Simple: one sentence, four numbers that always add up, and each day at a glance. --}}
@php
    $all = $selectedDay === 'all';
    $expected = $grid['totalExpected'];
    $came = $grid['rows']->count();
    $notCounted = $registrationCounts->except('confirmed')->filter();
    $cards = [
        ['Expected', $expected, 'Confirmed attendees. Support staff are counted in the staff report.', 'text-slate-900'],
        [$grid['presentLabel'], $came, $all ? 'Checked in on at least one day.' : 'Checked in during '.$grid['periodLabel'].'.', 'text-emerald-600'],
        [$grid['absentLabel'], $grid['absent']->count(), 'Expected, but no check-in'.($all ? ' on any day.' : '.'), 'text-rose-600'],
        [$all ? 'Average daily turnout' : 'Turnout', $grid['turnout'].'%', $all ? 'The average of each day\'s present ÷ expected.' : 'Present ÷ expected.', 'text-blue-600'],
    ];
@endphp

<section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    @if(! $grid['held'])
        <p class="text-lg font-bold text-slate-700">No check-ins have been recorded {{ $all ? 'for this event yet' : 'on '.$grid['periodLabel'] }}.</p>
        <p class="mt-1 text-sm text-slate-500">{{ number_format($expected) }} confirmed {{ \Illuminate\Support\Str::plural('attendee', $expected) }} are expected. Numbers will appear here as people check in.</p>
    @else
        <p class="text-lg font-bold leading-relaxed text-slate-800">
            @if($all)
                <span class="text-emerald-600">{{ number_format($came) }} of {{ number_format($expected) }}</span> confirmed attendees came at least once over
                {{ $grid['totalDays'] }} {{ \Illuminate\Support\Str::plural('day', $grid['totalDays']) }} of check-in.
                @if($grid['totalDays'] > 1) On average, <span class="text-blue-600">{{ $grid['turnout'] }}%</span> came each day. @endif
            @else
                <span class="text-emerald-600">{{ number_format($came) }} of {{ number_format($expected) }}</span> confirmed attendees were present on {{ $grid['periodLabel'] }} ({{ $grid['turnout'] }}%).
            @endif
        </p>
    @endif

    <div class="{{ $grid['held'] ? '' : 'hidden' }} mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach($cards as [$label, $value, $how, $colour])
            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-5">
                <p class="text-[11px] font-black uppercase tracking-wider text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-4xl font-black tracking-tight {{ $colour }}">{{ is_int($value) ? number_format($value) : $value }}</p>
                <p class="mt-2 text-xs leading-5 text-slate-500">{{ $how }}</p>
            </div>
        @endforeach
    </div>

    @if($all && $grid['dayStats']->count() > 0)
        <h2 class="mt-8 text-xs font-black uppercase tracking-widest text-slate-500">Each day</h2>
        <div class="mt-3 space-y-3">
            @foreach($grid['dayStats'] as $dayStat)
                <a href="{{ route('reports.event', ['event' => $event->id, 'day' => $dayStat['day']]) }}" class="block rounded-xl px-2 py-1.5 hover:bg-slate-50">
                    <span class="flex items-baseline justify-between gap-3 text-sm">
                        <span class="font-black text-slate-800">{{ $dayStat['label'] }}</span>
                        <span class="text-slate-600"><strong class="text-slate-900">{{ $dayStat['present'] }}</strong> of {{ $expected }} · {{ $dayStat['rate'] }}%</span>
                    </span>
                    <span class="mt-1.5 block h-3 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full bg-emerald-500" style="width: {{ $dayStat['rate'] }}%"></span></span>
                </a>
            @endforeach
        </div>
    @endif

    <div class="mt-6 space-y-1 border-t border-slate-100 pt-4 text-xs text-slate-500">
        @if($arrivedCount !== null)
            <p>{{ number_format($arrivedCount) }} of {{ number_format($registrationCounts['confirmed'] ?? 0) }} confirmed attendees have checked in on arrival. Anyone who never arrived counts as absent on each day.</p>
        @endif
        @if($notCounted->isNotEmpty())
            <p>Not counted: {{ $notCounted->map(fn ($count, $status) => $count.' '.str_replace('_', ' ', $status))->join(', ') }} {{ \Illuminate\Support\Str::plural('registration', $notCounted->sum()) }}.</p>
        @endif
        @if($grid['rows']->where('isStaff', true)->isNotEmpty())
            <p>Includes {{ $grid['rows']->where('isStaff', true)->count() }} numbered participant staff, who count as present on every day with check-ins.</p>
        @endif
        <p>Want the breakdown by day, area and person? Open <a href="{{ route('reports.event', ['event' => $event->id, 'day' => $selectedDay, 'view' => 'detailed']) }}" class="font-bold text-blue-600">Detailed</a>.</p>
    </div>
</section>
