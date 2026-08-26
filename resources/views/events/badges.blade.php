<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Badges &middot; {{ $event->title }}</title>
    <style>
        * { box-sizing: border-box; }
        :root { --ink:#0f172a; --muted:#64748b; --badge-w:{{ $event->badge_size === 'A5' ? '148mm' : '105mm' }}; --badge-h:{{ $event->badge_size === 'A5' ? '210mm' : '148mm' }}; }
        body { margin:0; font-family:Arial,sans-serif; background:#e2e8f0; color:var(--ink); }
        .toolbar { position:sticky; z-index:10; top:0; padding:14px 24px; background:#0f172a; color:white; display:flex; gap:18px; justify-content:space-between; align-items:center; }
        .toolbar small { display:block; margin-top:3px; color:#cbd5e1; }.actions{display:flex;gap:8px}
        .btn { padding:10px 16px; border:0; border-radius:8px; background:#2dd4bf; color:#042f2e; font-weight:800; cursor:pointer; text-decoration:none; }.btn.alt{background:white;color:#0f172a}
        .settings,.notice { max-width:1000px; margin:20px auto; padding:20px; border-radius:16px; background:white; }.notice{padding:12px 16px;background:#d1fae5;color:#065f46;font-weight:700}
        .settings h2{margin:0 0 5px}.settings-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px}.settings label{font-size:12px;font-weight:800;color:#475569}.settings select{display:block;width:100%;margin-top:7px;padding:10px;border:1px solid #cbd5e1;border-radius:8px}
        .colors{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}.color{display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid #e2e8f0;border-radius:10px}.color input[type=color]{width:38px;height:30px;border:0}
        .grid{display:grid;grid-template-columns:var(--badge-w);justify-content:center;gap:10mm;padding:10mm}.badge{--category:#0f766e;position:relative;width:var(--badge-w);height:var(--badge-h);overflow:hidden;border:1px solid #94a3b8;border-radius:5mm;display:flex;flex-direction:column;padding:7mm;text-align:left;page-break-after:always;background-color:#faf9f5;background-image:var(--badge-bg);background-position:center;background-repeat:no-repeat;background-size:cover}
        .brand{z-index:1;flex-shrink:0;min-height:19mm;padding:3mm 4mm;display:flex;align-items:center;gap:3mm;border:1px solid rgba(255,255,255,.75);border-radius:4mm;background:rgba(255,255,255,.91);box-shadow:0 2mm 6mm rgba(15,23,42,.08);overflow:hidden}.brand-logo,.event-logo{width:12mm;height:12mm;object-fit:contain;flex-shrink:0}.brand-initial{width:11mm;height:11mm;border-radius:50%;display:grid;place-items:center;background:#ccfbf1;color:#134e4a;font-size:4.5mm;font-weight:900;flex-shrink:0}.company-name{min-width:0;font-size:3.8mm;font-weight:900;letter-spacing:.02em;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
        .event-block{z-index:1;flex-shrink:0;margin:5mm 3mm 0;display:flex;align-items:center;gap:3mm;overflow:hidden}.event-copy{min-width:0}.event-title{margin:0;font-size:5.4mm;line-height:1.15;font-weight:900;color:#0f172a;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.event-meta{margin:1.5mm 0 0;color:#475569;font-size:2.9mm;line-height:1.35;font-weight:700;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
        .attendee{z-index:1;margin:auto 0 4mm;padding:7mm 5mm 6mm;border-left:1.5mm solid var(--category);border-radius:4mm;background:rgba(255,255,255,.94);box-shadow:0 3mm 9mm rgba(15,23,42,.12);overflow:hidden}.attendee-label{margin:0 0 2mm;color:var(--category);font-size:2.8mm;font-weight:900;letter-spacing:.2em;text-transform:uppercase}.name{margin:0;font-size:9mm;line-height:1.04;font-weight:900;color:#0f172a;overflow-wrap:anywhere;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.category{display:inline-block;margin-top:3mm;border-radius:999px;padding:1.3mm 4mm;background:var(--category);color:white;font-size:3mm;font-weight:900;max-width:70mm;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .credential{z-index:1;display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:end;gap:4mm;padding:4mm;border:1px solid rgba(255,255,255,.75);border-radius:4mm;background:rgba(255,255,255,.92);box-shadow:0 2mm 7mm rgba(15,23,42,.1)}.credential-copy{align-self:center;min-width:0}.credential-label{margin:0 0 1.5mm;color:#64748b;font-size:2.5mm;font-weight:900;letter-spacing:.16em;text-transform:uppercase}.member-id{margin:0;color:#0f172a;font-size:4mm;font-weight:900;overflow-wrap:anywhere}.scan{margin:3mm 0 0;color:#475569;font-size:2.5mm;font-weight:800;line-height:1.35}.qr{flex-shrink:0;padding:2mm;border:1.5px solid var(--category);border-radius:2.5mm;background:white;line-height:0}.qr svg{display:block;width:{{ $event->badge_size === 'A5' ? '44mm' : '31mm' }};height:{{ $event->badge_size === 'A5' ? '44mm' : '31mm' }}}.empty{padding:30px;background:white;text-align:center}
        @media(max-width:760px){.toolbar{align-items:flex-start;flex-direction:column}.settings-grid{grid-template-columns:1fr}.grid{padding:12px;overflow:auto}}
    </style>
</head>
<body>
    <div class="toolbar">
        <div><strong>{{ $event->title }} &middot; {{ $registrations->count() }} badge(s)</strong><small>{{ $event->badge_size }} &middot; Preview below, then download a print-ready PDF with one badge per page.</small></div>
        <div class="actions"><a class="btn alt" href="{{ route('events.registrations.index', $event) }}">Back</a><a class="btn" href="{{ route('events.badges.pdf', $event) }}">Download PDF</a></div>
    </div>
    @if(session('success'))<div class="notice">{{ session('success') }}</div>@endif
    <form class="settings" method="POST" action="{{ route('events.badges.settings', $event) }}">
        @csrf @method('PATCH')
        <h2>Badge design</h2><p style="margin:0;color:#64748b;font-size:14px">Choose the printed size and either retain the branded design or identify categories by colour.</p>
        <div class="settings-grid">
            <label>Badge size<select name="badge_size"><option value="A6" @selected($event->badge_size === 'A6')>A6 — 105 × 148 mm</option><option value="A5" @selected($event->badge_size === 'A5')>A5 — 148 × 210 mm</option></select></label>
            <label>Design<select name="badge_design" id="design"><option value="default" @selected($event->badge_design !== 'category')>Default branded design</option><option value="category" @selected($event->badge_design === 'category')>Colour by attendee category</option></select></label>
        </div>
        <div id="category-colors"><p style="margin:16px 0 0;font-size:12px;font-weight:800">CATEGORY COLOURS</p><div class="colors">
            @forelse($categories as $category)
                <label class="color"><input type="hidden" name="categories[]" value="{{ $category }}"><input type="color" name="colors[]" value="{{ $categoryColors[$category] }}"><span>{{ $category }}</span></label>
            @empty <span>No confirmed attendee categories yet.</span> @endforelse
        </div></div>
        <button class="btn" style="margin-top:18px">Save and update preview</button>
    </form>
    <main class="grid">
        @forelse($registrations as $registration)
            @php
                $category = $registration->participant->category ?: 'Attendee';
                $color = $categoryColors[$category] ?? '#0F766E';
                $nameParts = preg_split('/\s+/u', trim($registration->participant->name), -1, PREG_SPLIT_NO_EMPTY);
                if (count($nameParts) >= 3) {
                    $middleInitials = array_map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)).'.', array_slice($nameParts, 1, -1));
                    $badgeName = implode(' ', [$nameParts[0], ...$middleInitials, $nameParts[array_key_last($nameParts)]]);
                } else {
                    $badgeName = $registration->participant->name;
                }
                $hasMemberId = filled($registration->participant->member_id);
            @endphp
            <article class="badge" style="--badge-bg:url('{{ asset('images/badges/professional-teal-background-v1.png') }}');--category:{{ $event->badge_design === 'category' ? $color : '#0F766E' }}">
                <header class="brand">
                    @if($event->company?->logo_path)<img class="brand-logo" src="{{ Storage::url($event->company->logo_path) }}" alt="{{ $event->company->name }} logo">@else<span class="brand-initial">{{ strtoupper(substr($event->company?->name ?? 'O', 0, 1)) }}</span>@endif
                    <span class="company-name">{{ $event->company?->name ?? 'Event Organizer' }}</span>
                </header>
                <section class="event-block">@if($event->logo_path)<img class="event-logo" src="{{ Storage::url($event->logo_path) }}" alt="{{ $event->title }} logo">@endif<div class="event-copy"><p class="event-title">{{ $event->title }}</p><p class="event-meta">{{ $event->event_date->format('j M Y') }}@if($event->end_date && !$event->end_date->equalTo($event->event_date)) &ndash; {{ $event->end_date->format('j M Y') }}@endif @if($event->location)<br>{{ $event->location }}@endif</p></div></section>
                <section class="attendee"><p class="attendee-label">Attendee</p><h1 class="name" title="{{ $registration->participant->name }}">{{ $badgeName }}</h1><span class="category">{{ $category }}</span></section>
                <footer class="credential"><div class="credential-copy"><p class="credential-label">{{ $hasMemberId ? 'Member ID' : 'Credential' }}</p><p class="member-id">{{ $hasMemberId ? $registration->participant->member_id : 'Event Pass' }}</p><p class="scan">Attendance &amp; meal collection</p></div><div class="qr">{!! QrCode::size(220)->margin(1)->generate('ASAH-ATTENDANCE:'.$registration->registration_code) !!}</div></footer>
            </article>
        @empty <p class="empty">No confirmed attendees yet.</p> @endforelse
    </main>
    <script>const d=document.getElementById('design'),c=document.getElementById('category-colors');function toggle(){c.style.display=d.value==='category'?'block':'none'}d.addEventListener('change',toggle);toggle()</script>
</body></html>
