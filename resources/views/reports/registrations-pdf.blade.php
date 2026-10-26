<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    @include('reports._pdf-style')
</head>
<body>
    @php
        $rows = $report['rows'];
        $label = \App\Services\RegistrationReport::filterLabel($report['filters']);
        $categoryBreakdown = $report['breakdowns']['Category'];
        $genderBreakdown = $report['breakdowns']['Gender'];
        $countryBreakdown = $report['breakdowns']['Country'];
        $areaBreakdown = $report['breakdowns']['Area'];
    @endphp
    <div class="footer">{{ $event->title }} · Registrations · {{ $label }} <span class="page" style="float: right"></span></div>

    <div class="band">
        <div class="kicker">Registration report · {{ $label }}</div>
        <h1>{{ $event->title }}</h1>
        <div class="meta">
            {{ $event->event_date->format('j M Y') }}@if($event->end_date && ! $event->end_date->isSameDay($event->event_date)) – {{ $event->end_date->format('j M Y') }}@endif
            · Everyone registered, whether or not they have checked in. Country comes from the phone number. Generated {{ now()->format('j M Y, g:i A') }}
        </div>
    </div>

    <table class="stats"><tr>
        <td><div class="label">People</div><div class="value">{{ number_format($rows->count()) }}</div></td>
        <td class="good"><div class="label">Checked in at least once</div><div class="value">{{ number_format($report['checkedIn']) }}</div></td>
        <td><div class="label">Not checked in yet</div><div class="value">{{ number_format($rows->count() - $report['checkedIn']) }}</div></td>
    </tr></table>

    @include('reports._pdf-breakdowns')

    <h2>People <span class="count">({{ $rows->count() }})</span></h2>
    <table class="list">
        <thead><tr><th class="n">#</th><th>Name</th><th>Phone</th><th>Country</th><th>Area</th><th>Category</th><th>Gender</th><th>Status</th><th>Registered</th><th>Checked in</th></tr></thead>
        <tbody>
            @forelse($rows as $index => $row)
                <tr>
                    <td class="n">{{ $index + 1 }}</td>
                    <td><strong>{{ $row->person->name }}</strong></td>
                    <td>{{ $row->person->phone ?: '—' }}</td>
                    <td>{{ $row->country }}</td>
                    <td>{{ $row->area }}</td>
                    <td>{{ $row->category }}</td>
                    <td>{{ $row->gender }}</td>
                    <td>{{ $row->statusLabel }}</td>
                    <td>{{ $row->registration->registered_at?->format('j M Y') }}</td>
                    <td class="{{ $row->daysCheckedIn ? 'yes' : 'muted' }}">{{ \App\Services\RegistrationReport::checkedInLabel($row, $report['eventDays']) }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="muted">Nobody has registered with this selection yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
