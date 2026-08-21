<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attendee Badges &middot; {{ $event->title }}</title>
    <style>
        * { box-sizing: border-box; }
        :root { --ink: #0f172a; --muted: #64748b; --brand: #0f766e; --brand-dark: #134e4a; --line: #cbd5e1; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #e2e8f0; color: var(--ink); }
        .toolbar { position: sticky; z-index: 10; top: 0; padding: 14px 24px; background: var(--ink); color: white; display: flex; gap: 20px; justify-content: space-between; align-items: center; }
        .toolbar strong, .toolbar small { display: block; }
        .toolbar small { margin-top: 3px; color: #cbd5e1; }
        .toolbar button { padding: 10px 18px; border: 0; border-radius: 8px; background: #2dd4bf; color: #042f2e; font-weight: 800; cursor: pointer; }
        .grid { display: grid; grid-template-columns: repeat(2, 88.9mm); justify-content: center; gap: 8mm; padding: 10mm; }
        .badge { position: relative; width: 88.9mm; min-height: 127mm; overflow: hidden; border: 1px solid var(--line); border-radius: 5mm; background: white; display: flex; flex-direction: column; text-align: center; page-break-inside: avoid; break-inside: avoid; }
        .badge::before { content: ''; position: absolute; inset: 0 0 auto; height: 5mm; background: linear-gradient(90deg, var(--brand-dark), #14b8a6); }
        .brand { min-height: 28mm; padding: 11mm 7mm 4mm; display: flex; align-items: center; justify-content: center; gap: 3mm; border-bottom: 1px solid #e2e8f0; }
        .brand-logo { width: 14mm; height: 14mm; object-fit: contain; }
        .brand-initial { width: 13mm; height: 13mm; border-radius: 50%; display: grid; place-items: center; background: #ccfbf1; color: var(--brand-dark); font-size: 6mm; font-weight: 900; }
        .company-name { max-width: 55mm; font-size: 4.2mm; font-weight: 800; line-height: 1.15; text-align: left; }
        .event-block { padding: 5mm 7mm 3mm; display: flex; align-items: center; justify-content: center; gap: 3mm; }
        .event-logo { width: 12mm; height: 12mm; object-fit: contain; }
        .event-title { margin: 0; font-size: 5mm; line-height: 1.15; font-weight: 900; color: var(--brand-dark); }
        .event-meta { margin: 1.5mm 0 0; color: var(--muted); font-size: 2.8mm; line-height: 1.4; }
        .attendee { padding: 2mm 7mm 0; }
        .attendee-label { margin: 0 0 1mm; color: var(--muted); font-size: 2.4mm; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; }
        .name { margin: 0; font-size: 7mm; line-height: 1.08; font-weight: 900; overflow-wrap: anywhere; }
        .details { min-height: 9mm; margin-top: 2mm; display: flex; flex-wrap: wrap; justify-content: center; gap: 1.5mm; }
        .pill { border-radius: 999px; padding: 1mm 3mm; background: #f1f5f9; color: #334155; font-size: 2.7mm; font-weight: 800; }
        .qr-wrap { margin: auto auto 3mm; padding: 2.5mm; border: 1px solid #cbd5e1; border-radius: 3mm; background: white; line-height: 0; }
        .qr-wrap svg { width: 35mm; height: 35mm; }
        .scan-label { margin: 0 0 5mm; color: var(--muted); font-size: 2.5mm; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
        .empty { grid-column: 1 / -1; padding: 30px; border-radius: 16px; background: white; text-align: center; }
        @page { size: A4 portrait; margin: 8mm; }
        @media print {
            body { background: white; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .toolbar { display: none; }
            .grid { gap: 6mm; padding: 0; }
            .badge { border-color: #94a3b8; }
        }
        @media (max-width: 760px) {
            .grid { grid-template-columns: 88.9mm; padding: 16px; }
            .toolbar { align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <strong>{{ $event->title }} &middot; {{ $registrations->count() }} badge(s)</strong>
            <small>Print at 100% scale on A4 paper for two badges per row.</small>
        </div>
        <button type="button" onclick="window.print()">Print badges</button>
    </div>

    <main class="grid">
        @forelse($registrations as $registration)
            <article class="badge">
                <header class="brand">
                    @if($event->company?->logo_path)
                        <img class="brand-logo" src="{{ Storage::url($event->company->logo_path) }}" alt="{{ $event->company->name }} logo">
                    @else
                        <span class="brand-initial">{{ strtoupper(substr($event->company?->name ?? 'O', 0, 1)) }}</span>
                    @endif
                    <span class="company-name">{{ $event->company?->name ?? 'Event Organizer' }}</span>
                </header>

                <section class="event-block">
                    @if($event->logo_path)
                        <img class="event-logo" src="{{ Storage::url($event->logo_path) }}" alt="{{ $event->title }} logo">
                    @endif
                    <div>
                        <p class="event-title">{{ $event->title }}</p>
                        <p class="event-meta">
                            {{ $event->event_date->format('j M Y') }}@if($event->end_date && !$event->end_date->equalTo($event->event_date)) &ndash; {{ $event->end_date->format('j M Y') }}@endif
                            @if($event->location)<br>{{ $event->location }}@endif
                        </p>
                    </div>
                </section>

                <section class="attendee">
                    <p class="attendee-label">Attendee</p>
                    <h1 class="name">{{ $registration->participant->name }}</h1>
                    <div class="details">
                        <span class="pill">{{ $registration->participant->category ?: 'Attendee' }}</span>
                        @if($registration->participant->member_id)
                            <span class="pill">ID: {{ $registration->participant->member_id }}</span>
                        @endif
                    </div>
                </section>

                <div class="qr-wrap" aria-label="Attendance QR code">{!! QrCode::size(180)->margin(1)->generate('ASAH-ATTENDANCE:'.$registration->registration_code) !!}</div>
                <p class="scan-label">Scan for attendance</p>
            </article>
        @empty
            <p class="empty">No confirmed attendees yet.</p>
        @endforelse
    </main>
</body>
</html>
