<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; color: #1e293b; font-size: 11px; }
        h1 { font-size: 20px; margin: 0 0 4px 0; }
        .meta { color: #64748b; font-size: 10px; margin-bottom: 18px; }
        .stats { width: 100%; margin-bottom: 18px; }
        .stats td { width: 50%; padding: 10px; border: 1px solid #e2e8f0; text-align: center; }
        .stats .label { display: block; font-size: 9px; text-transform: uppercase; color: #94a3b8; margin-bottom: 4px; }
        .stats .value { font-size: 18px; font-weight: bold; }
        h2 { font-size: 13px; margin: 18px 0 8px 0; border-bottom: 2px solid #1e293b; padding-bottom: 4px; }
        table.list { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.list th { text-align: left; font-size: 9px; text-transform: uppercase; color: #64748b; background: #f8fafc; padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
        table.list td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; font-size: 10px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; background: #f1f5f9; font-size: 9px; margin: 2px; }
    </style>
</head>
<body>
    <h1>{{ $event->title }}</h1>
    <div class="meta">Full summary report &middot; Generated {{ now()->format('M j, Y g:i A') }}</div>

    <table class="stats">
        <tr>
            <td><span class="label">Unique Attendees</span><span class="value">{{ $presentUsers->count() }}</span></td>
            <td><span class="label">Never Attended</span><span class="value">{{ $absentUsers->count() }}</span></td>
        </tr>
    </table>

    <h2>Attendees by category</h2>
    <div>
        @forelse($categoryBreakdown as $label => $count)
            <span class="badge">{{ $label }} &middot; {{ $count }}</span>
        @empty
            <span>No attendance recorded.</span>
        @endforelse
    </div>

    <h2>Attendees by gender</h2>
    <div>
        @forelse($genderBreakdown as $label => $count)
            <span class="badge">{{ $label }} &middot; {{ $count }}</span>
        @empty
            <span>No attendance recorded.</span>
        @endforelse
    </div>

    <h2>Member consistency rankings</h2>
    <table class="list">
        <thead><tr><th>Name</th><th>Category</th><th>Days attended</th><th>Retention rate</th></tr></thead>
        <tbody>
            @forelse($presentUsers->sortByDesc('days_attended') as $user)
                <tr><td>{{ $user->name }}</td><td>{{ $user->category ?? 'General Attendee' }}</td><td>{{ $user->days_attended }} / {{ $totalEventDays }}</td><td>{{ round($user->attendance_rate) }}%</td></tr>
            @empty
                <tr><td colspan="4">No attendance records found.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
