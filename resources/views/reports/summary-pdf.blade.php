<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    @include('reports._pdf-style')
</head>
<body>
    @php
        $slots = $grid['slots'];
        $expected = $grid['totalExpected'];
        $countFor = fn ($slot) => $grid['rows']->filter(fn ($row) => ! in_array($row->cells[$slot['key']], ['', \App\Services\AttendanceGrid::NOT_PRESENT], true))->count();
        $people = $grid['rows']->sortBy([['daysAttended', 'desc'], [fn ($a, $b) => strcasecmp($a->person->name, $b->person->name)]])->values();
    @endphp
    <div class="footer">{{ $event->title }} · Full summary <span class="page" style="float: right"></span></div>

    <div class="band">
        <div class="kicker">Full summary · all days</div>
        <h1>{{ $event->title }}</h1>
        <div class="meta">{{ $totalEventDays }} {{ \Illuminate\Support\Str::plural('day', $totalEventDays) }} · Generated {{ now()->format('j M Y, g:i A') }}</div>
    </div>

    <table class="stats"><tr>
        <td><div class="label">Registered</div><div class="value">{{ number_format($registeredCount) }}</div></td>
        <td><div class="label">Confirmed</div><div class="value">{{ number_format($confirmedCount) }}</div></td>
        <td><div class="label">Arrived</div><div class="value">{{ number_format($arrivedCount) }}</div></td>
        <td class="good"><div class="label">Present at least once</div><div class="value">{{ number_format($presentUsers->count()) }}</div></td>
        <td class="bad"><div class="label">Never present</div><div class="value">{{ number_format($absentUsers->count()) }}</div></td>
    </tr></table>

    <h2>Present each day</h2>
    <table class="list">
        <thead><tr><th>Day</th><th class="c" style="width: 70px">People</th><th style="width: 45%">Of expected ({{ $expected }})</th></tr></thead>
        <tbody>
            @foreach($slots as $slot)
                @php
                    $n = $countFor($slot);
                    $pct = $expected ? round($n / $expected * 100) : 0;
                @endphp
                <tr><td><strong>{{ $slot['label'] }}</strong></td><td class="c">{{ $n }}</td><td><table style="width: 100%; border-collapse: collapse"><tr><td style="padding: 0; border: 0; background: none"><div class="bar"><div style="width: {{ $pct }}%"></div></div></td><td style="padding: 0 0 0 6px; border: 0; background: none; width: 30px" class="muted">{{ $pct }}%</td></tr></table></td></tr>
            @endforeach
        </tbody>
    </table>

    @include('reports._pdf-breakdowns', ['areaBreakdown' => $grid['rows']->countBy('area')->sortDesc()])

    <h2>Attendance by person <span class="count">({{ $people->count() }}, most days first)</span></h2>
    <table class="list">
        <thead><tr>
            <th class="n">#</th><th>Name</th><th>Area</th><th>Category</th>
            @foreach($slots as $slot)<th class="c">{{ $slot['label'] }}</th>@endforeach
            <th class="c">Days</th>
        </tr></thead>
        <tbody>
            @forelse($people as $index => $row)
                <tr>
                    <td class="n">{{ $index + 1 }}</td>
                    <td><strong>{{ $row->person->name }}</strong></td>
                    <td>{{ $row->area }}</td>
                    <td>{{ $row->person->category ?: '—' }}</td>
                    @foreach($slots as $slot)<td class="c">@include('reports._pdf-day-cell', ['value' => $row->cells[$slot['key']]])</td>@endforeach
                    <td class="c"><strong>{{ $row->daysAttended }}</strong> of {{ $grid['totalDays'] }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ 5 + $slots->count() }}" class="muted">No attendance records found.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
