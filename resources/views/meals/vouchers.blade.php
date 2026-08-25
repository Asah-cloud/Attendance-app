<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Food Vouchers &middot; {{ $event->title }}</title>
    <style>
        * { box-sizing: border-box; }
        :root { --ink: #0f172a; --muted: #64748b; --brand: #b45309; --line: #cbd5e1; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #e2e8f0; color: var(--ink); }
        .toolbar { position: sticky; z-index: 10; top: 0; padding: 14px 24px; background: var(--ink); color: white; display: flex; gap: 20px; justify-content: space-between; align-items: center; }
        .toolbar small { display: block; margin-top: 3px; color: #cbd5e1; }
        .toolbar button { padding: 10px 18px; border: 0; border-radius: 8px; background: #fbbf24; color: #451a03; font-weight: 800; cursor: pointer; }
        .grid { display: grid; grid-template-columns: repeat(2, 88.9mm); justify-content: center; gap: 8mm; padding: 10mm; }
        .voucher { position: relative; width: 88.9mm; min-height: 100mm; overflow: hidden; border: 1px dashed var(--line); border-radius: 5mm; background: white; display: flex; flex-direction: column; text-align: center; page-break-inside: avoid; break-inside: avoid; padding: 6mm; }
        .label { color: var(--brand); font-size: 2.6mm; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; }
        .name { margin: 2mm 0 0; font-size: 6mm; font-weight: 900; overflow-wrap: anywhere; }
        .category { margin: 1mm 0 0; color: var(--muted); font-size: 3mm; }
        .entitlements { margin: 3mm 0 0; font-size: 2.6mm; color: #334155; line-height: 1.6; text-align: left; }
        .qr-wrap { margin: 3mm auto; padding: 2.5mm; border: 1px solid #cbd5e1; border-radius: 3mm; background: white; line-height: 0; }
        .qr-wrap svg { width: 30mm; height: 30mm; }
        .scan-label { margin: 0; color: var(--muted); font-size: 2.4mm; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
        .empty { grid-column: 1 / -1; padding: 30px; border-radius: 16px; background: white; text-align: center; }
        @page { size: A4 portrait; margin: 8mm; }
        @media print {
            body { background: white; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .toolbar { display: none; }
            .grid { gap: 6mm; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <strong>{{ $event->title }} &middot; {{ $registrations->count() }} voucher(s)</strong>
            <small>Backup fallback if an attendee's badge or phone is unavailable — same QR as their attendance badge.</small>
        </div>
        <button type="button" onclick="window.print()">Print vouchers</button>
    </div>

    <main class="grid">
        @forelse($registrations as $registration)
            <article class="voucher">
                <p class="label">Food voucher</p>
                <h1 class="name">{{ $registration->participant->name }}</h1>
                <p class="category">{{ $registration->participant->category ?: 'Attendee' }}</p>
                @if($meals->isNotEmpty())
                    <div class="entitlements">
                        @foreach($meals as $meal)
                            <div>{{ $meal->name }}: up to {{ $meal->entitlementFor($registration->participant->category) }} portion(s)</div>
                        @endforeach
                    </div>
                @endif
                <div class="qr-wrap" aria-label="Food QR code">{!! QrCode::size(150)->margin(1)->generate('ASAH-ATTENDANCE:'.$registration->registration_code) !!}</div>
                <p class="scan-label">Scan at any food station</p>
            </article>
        @empty
            <p class="empty">No confirmed attendees yet.</p>
        @endforelse
    </main>
</body>
</html>
