<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    @include('reports._pdf-style')
</head>
<body>
    @php
        $slots = $grid['slots'];
        $all = $grid['day'] === 'all';
        $expected = $grid['totalExpected'];
        $presentCount = $grid['rows']->count();
        // All days: most days first, so the most faithful attendees lead the list.
        $people = $all
            ? $grid['rows']->sortBy([['daysAttended', 'desc'], [fn ($a, $b) => strcasecmp($a->person->name, $b->person->name)]])->values()
            : $grid['rows'];
        $categoryBreakdown = $grid['rows']->countBy(fn ($row) => $row->person->category ?: 'Unspecified')->sortDesc();
        $genderBreakdown = $grid['rows']->countBy(fn ($row) => $row->person->gender ?: 'Unspecified')->sortDesc();
        $areaBreakdown = $grid['rows']->countBy('area')->sortDesc();
        $countryBreakdown = $grid['rows']->countBy('country')->sortDesc();
        $filterLabel = \App\Services\AttendanceGrid::filterLabel($grid['filters']);
    @endphp
    <div class="footer">{{ $event->title }} · Attendance · {{ $grid['periodLabel'] }} <span class="page" style="float: right"></span></div>

    <div class="band">
        <div class="kicker">Attendance report · {{ $grid['periodLabel'] }}{{ $filterLabel ? ' · '.$filterLabel : '' }}</div>
        <h1>{{ $event->title }}</h1>
        <div class="meta">
            @if($all)
                {{ $grid['totalDays'] }} {{ \Illuminate\Support\Str::plural('day', $grid['totalDays']) }} with check-ins (of {{ $grid['eventDays'] }} event {{ \Illuminate\Support\Str::plural('day', $grid['eventDays']) }}).
            @endif
            Expected = confirmed attendees. Generated {{ now()->format('j M Y, g:i A') }}
        </div>
    </div>

    <table class="stats"><tr>
        <td><div class="label">Expected</div><div class="value">{{ number_format($expected) }}</div></td>
        <td class="good"><div class="label">{{ $grid['presentLabel'] }}</div><div class="value">{{ number_format($presentCount) }}</div></td>
        <td class="bad"><div class="label">{{ $grid['absentLabel'] }}</div><div class="value">{{ number_format($grid['absent']->count()) }}</div></td>
        <td><div class="label">{{ $all ? 'Average daily turnout' : 'Turnout' }}</div><div class="value">{{ $grid['turnout'] }}%</div></td>
    </tr></table>

    @if(! $grid['held'])
        <p class="muted" style="font-size: 11px; margin-top: 16px">No check-ins have been recorded {{ $all ? 'for this event yet' : 'on '.$grid['periodLabel'] }}.</p>
    @else
        @if($slots->count() > 1)
            <h2>Each day</h2>
            <table class="list">
                <thead><tr><th>Day</th><th class="c" style="width: 60px">Present</th><th class="c" style="width: 60px">Absent</th><th style="width: 45%">Turnout</th></tr></thead>
                <tbody>
                    @foreach($slots as $slot)
                        @php
                            $stat = $grid['slotStats'][$slot['key']];
                        @endphp
                        <tr><td><strong>{{ $slot['label'] }}</strong></td><td class="c">{{ $stat['present'] }}</td><td class="c">{{ max(0, $expected - $stat['present']) }}</td><td><table style="width: 100%; border-collapse: collapse"><tr><td style="padding: 0; border: 0; background: none"><div class="bar"><div style="width: {{ $stat['rate'] }}%"></div></div></td><td style="padding: 0 0 0 6px; border: 0; background: none; width: 30px" class="muted">{{ $stat['rate'] }}%</td></tr></table></td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @include('reports._pdf-breakdowns')

        <h2>{{ $grid['presentLabel'] }} <span class="count">({{ $presentCount }}{{ $all && $grid['multiDay'] ? ', most days first' : '' }})</span></h2>
        <table class="list">
            <thead><tr>
                <th class="n">#</th><th>Name</th><th>Phone</th><th>Area</th><th>Category</th>
                @foreach($slots as $slot)<th class="c">{{ $slot['label'] }}</th>@endforeach
                @if($grid['multiDay'])<th class="c">Days</th>@endif
            </tr></thead>
            <tbody>
                @foreach($people as $index => $row)
                    <tr>
                        <td class="n">{{ $index + 1 }}</td>
                        <td><strong>{{ $row->person->name }}</strong></td>
                        <td>{{ $row->person->phone ?: '—' }}</td>
                        <td>{{ $row->area }}</td>
                        <td>{{ $row->person->category ?: '—' }}</td>
                        @foreach($slots as $slot)<td class="c">@include('reports._pdf-day-cell', ['value' => $row->cells[$slot['key']]])</td>@endforeach
                        @if($grid['multiDay'])<td class="c"><strong>{{ $row->daysAttended }}</strong> of {{ $grid['totalDays'] }}</td>@endif
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>{{ $grid['absentLabel'] }} <span class="count">({{ $grid['absent']->count() }})</span></h2>
        <table class="list">
            <thead><tr><th class="n">#</th><th>Name</th><th>Phone</th><th>Area</th><th>Category</th><th>Gender</th></tr></thead>
            <tbody>
                @forelse($grid['absent'] as $index => $person)
                    <tr><td class="n">{{ $index + 1 }}</td><td><strong>{{ $person->name }}</strong></td><td>{{ $person->phone ?: '—' }}</td><td>{{ \App\Services\AttendanceGrid::area($person) }}</td><td>{{ $person->category ?: '—' }}</td><td>{{ $person->gender ?: '—' }}</td></tr>
                @empty
                    <tr><td colspan="6" class="muted">Everyone expected checked in.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif
</body>
</html>
