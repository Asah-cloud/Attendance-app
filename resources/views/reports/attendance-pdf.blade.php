<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    @include('reports._pdf-style')
</head>
<body>
    @php
        $slots = $grid['slots'];
        $presentCount = $grid['rows']->count();
        $rate = $totalExpected ? round($presentCount / $totalExpected * 100) : 0;
        $countFor = fn ($slot) => $grid['rows']->filter(fn ($row) => ! in_array($row->cells[$slot['key']], ['', \App\Services\AttendanceGrid::NOT_PRESENT], true))->count();
    @endphp
    <div class="footer">{{ $event->title }} · Attendance · {{ $grid['periodLabel'] }} <span class="page" style="float: right"></span></div>

    <div class="band">
        <div class="kicker">Attendance report · {{ $grid['periodLabel'] }}</div>
        <h1>{{ $event->title }}</h1>
        <div class="meta">Each person is counted once{{ $grid['multiDay'] ? ', even if they came on several days' : '' }}. Numbered participant staff are included. Generated {{ now()->format('j M Y, g:i A') }}</div>
    </div>

    <table class="stats"><tr>
        <td><div class="label">Expected</div><div class="value">{{ number_format($totalExpected) }}</div></td>
        <td class="good"><div class="label">{{ $grid['multiDay'] ? 'Present at least once' : 'Present' }}</div><div class="value">{{ number_format($presentCount) }}</div></td>
        <td class="bad"><div class="label">{{ $grid['multiDay'] ? 'Never present' : 'Not present' }}</div><div class="value">{{ number_format($grid['absent']->count()) }}</div></td>
        <td><div class="label">Turnout</div><div class="value">{{ $rate }}%</div></td>
    </tr></table>

    @if($slots->count() > 1)
        <h2>Present each day</h2>
        <table class="list">
            <thead><tr><th>Day</th><th class="c" style="width: 70px">People</th><th style="width: 45%">Of expected</th></tr></thead>
            <tbody>
                @foreach($slots as $slot)
                    @php
                        $n = $countFor($slot);
                        $pct = $totalExpected ? round($n / $totalExpected * 100) : 0;
                    @endphp
                    <tr><td><strong>{{ $slot['label'] }}</strong></td><td class="c">{{ $n }}</td><td><table style="width: 100%; border-collapse: collapse"><tr><td style="padding: 0; border: 0; background: none"><div class="bar"><div style="width: {{ $pct }}%"></div></div></td><td style="padding: 0 0 0 6px; border: 0; background: none; width: 30px" class="muted">{{ $pct }}%</td></tr></table></td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @include('reports._pdf-breakdowns')

    <h2>Present <span class="count">({{ $presentCount }})</span></h2>
    <table class="list">
        <thead><tr>
            <th class="n">#</th><th>Name</th><th>Phone</th><th>Area</th><th>Category</th>
            @foreach($slots as $slot)<th class="c">{{ $slot['label'] }}</th>@endforeach
            @if($grid['multiDay'])<th class="c">Days</th>@endif
        </tr></thead>
        <tbody>
            @forelse($grid['rows'] as $index => $row)
                <tr>
                    <td class="n">{{ $index + 1 }}</td>
                    <td><strong>{{ $row->person->name }}</strong></td>
                    <td>{{ $row->person->phone ?: '—' }}</td>
                    <td>{{ $row->area }}</td>
                    <td>{{ $row->person->category ?: '—' }}</td>
                    @foreach($slots as $slot)<td class="c">@include('reports._pdf-day-cell', ['value' => $row->cells[$slot['key']]])</td>@endforeach
                    @if($grid['multiDay'])<td class="c"><strong>{{ $row->daysAttended }}</strong> of {{ $grid['totalDays'] }}</td>@endif
                </tr>
            @empty
                <tr><td colspan="{{ 5 + $slots->count() + ($grid['multiDay'] ? 1 : 0) }}" class="muted">No attendance records found.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>{{ $grid['multiDay'] ? 'Never present' : 'Not present' }} <span class="count">({{ $grid['absent']->count() }})</span></h2>
    <table class="list">
        <thead><tr><th class="n">#</th><th>Name</th><th>Phone</th><th>Area</th><th>Category</th><th>Gender</th></tr></thead>
        <tbody>
            @forelse($grid['absent'] as $index => $person)
                <tr><td class="n">{{ $index + 1 }}</td><td><strong>{{ $person->name }}</strong></td><td>{{ $person->phone ?: '—' }}</td><td>{{ \App\Services\AttendanceGrid::area($person) }}</td><td>{{ $person->category ?: '—' }}</td><td>{{ $person->gender ?: '—' }}</td></tr>
            @empty
                <tr><td colspan="6" class="muted">Everyone expected was present.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
