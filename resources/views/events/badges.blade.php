<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attendee Badges &middot; {{ $event->title }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #f1f5f9; color: #0f172a; }
        .toolbar { position: sticky; top: 0; padding: 16px 24px; background: #0f172a; color: white; display: flex; justify-content: space-between; align-items: center; }
        .toolbar button { padding: 10px 18px; border: 0; border-radius: 8px; background: #2563eb; color: white; font-weight: bold; cursor: pointer; }
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; padding: 24px; max-width: 900px; margin: 0 auto; }
        .badge { border: 2px dashed #cbd5e1; border-radius: 16px; padding: 20px; background: white; display: flex; flex-direction: column; align-items: center; text-align: center; page-break-inside: avoid; }
        .badge .qr { margin-bottom: 10px; }
        .badge .name { font-size: 18px; font-weight: bold; margin: 0 0 4px; }
        .badge .category { font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; background: #f1f5f9; border-radius: 999px; padding: 3px 12px; margin-bottom: 6px; }
        .badge .event { font-size: 11px; color: #94a3b8; margin-top: 8px; }
        @media print {
            .toolbar { display: none; }
            body { background: white; }
            .badge { border: 1px solid #cbd5e1; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <span>{{ $event->title }} &middot; {{ $registrations->count() }} badge(s)</span>
        <button onclick="window.print()">Print badges</button>
    </div>

    <div class="grid">
        @forelse($registrations as $registration)
            <div class="badge">
                <div class="qr">{!! QrCode::size(140)->margin(0)->generate('ASAH-ATTENDANCE:'.$registration->registration_code) !!}</div>
                <p class="name">{{ $registration->participant->name }}</p>
                <span class="category">{{ $registration->participant->category ?: 'Attendee' }}</span>
                <p class="event">{{ $event->title }}</p>
            </div>
        @empty
            <p>No confirmed attendees yet.</p>
        @endforelse
    </div>
</body>
</html>
