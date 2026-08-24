<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; color: #1e293b; font-size: 11px; }
        h1 { font-size: 20px; margin: 0 0 4px 0; }
        .meta { color: #64748b; font-size: 10px; margin-bottom: 18px; }
        table.list { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.list th { text-align: left; font-size: 9px; text-transform: uppercase; color: #64748b; background: #f8fafc; padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
        table.list td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; font-size: 10px; }
    </style>
</head>
<body>
    <h1>{{ $form->title }}</h1>
    <div class="meta">
        {{ $event->title }} &middot; Form responses &middot; Generated {{ now()->format('M j, Y g:i A') }} &middot; {{ $responses->count() }} response(s)
    </div>

    <table class="list">
        <thead><tr>
            <th>Submitted</th>
            @foreach($fields as $field)<th>{{ $field->label }}</th>@endforeach
        </tr></thead>
        <tbody>
            @forelse($responses as $response)
                <tr>
                    <td>{{ $response->created_at->format('M j, Y g:i A') }}</td>
                    @foreach($fields as $field)<td>{{ $response->answers[$field->field_key] ?? '—' }}</td>@endforeach
                </tr>
            @empty
                <tr><td colspan="{{ $fields->count() + 1 }}">No responses yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
