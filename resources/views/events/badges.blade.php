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
        .grid{display:grid;grid-template-columns:var(--badge-w);justify-content:center;gap:10mm;padding:10mm}.badge{--category:#0f766e;position:relative;width:var(--badge-w);height:var(--badge-h);overflow:hidden;border:1px solid #94a3b8;border-radius:5mm;background:white;display:flex;flex-direction:column;text-align:center;page-break-after:always}
        .badge:before,.badge:after{content:'';position:absolute;pointer-events:none}.badge:before{inset:0 0 auto;height:8mm;background:linear-gradient(105deg,var(--category),#5eead4)}.badge:after{width:70mm;height:70mm;right:-40mm;bottom:-40mm;border-radius:50%;border:12mm solid color-mix(in srgb,var(--category),white 84%)}
        .category-design{background:linear-gradient(150deg,color-mix(in srgb,var(--category),white 90%),white 45%)}.category-design:before{height:13mm;background:var(--category)}
        .brand{z-index:1;min-height:35mm;padding:15mm 9mm 5mm;display:flex;align-items:center;justify-content:center;gap:4mm;border-bottom:1px solid #e2e8f0}.brand-logo,.event-logo{width:18mm;height:18mm;object-fit:contain}.brand-initial{width:17mm;height:17mm;border-radius:50%;display:grid;place-items:center;background:#ccfbf1;font-size:7mm;font-weight:900}.company-name{max-width:85mm;font-size:5.5mm;font-weight:800;text-align:left}
        .event-block{z-index:1;padding:7mm 9mm 4mm;display:flex;align-items:center;justify-content:center;gap:4mm}.event-title{margin:0;font-size:7mm;font-weight:900;color:var(--category)}.event-meta{margin:2mm 0 0;color:var(--muted);font-size:3.5mm;line-height:1.4}.attendee{z-index:1;padding:4mm 9mm 0}.attendee-label{margin:0 0 2mm;color:var(--muted);font-size:3mm;font-weight:800;letter-spacing:.18em;text-transform:uppercase}.name{margin:0;font-size:10mm;line-height:1.08;font-weight:900;overflow-wrap:anywhere}.details{margin-top:3mm;display:flex;justify-content:center;gap:2mm}.pill{border-radius:999px;padding:1.5mm 4mm;background:color-mix(in srgb,var(--category),white 86%);color:var(--category);font-size:3.3mm;font-weight:800}
        .qr{z-index:1;margin:auto auto 4mm;padding:3mm;border:1px solid #cbd5e1;border-radius:3mm;background:white;line-height:0}.qr svg{width:{{ $event->badge_size === 'A5' ? '48mm' : '38mm' }};height:{{ $event->badge_size === 'A5' ? '48mm' : '38mm' }}}.scan{z-index:1;margin:0 0 7mm;color:var(--muted);font-size:3mm;font-weight:700;text-transform:uppercase}.empty{padding:30px;background:white;text-align:center}
        @page { size:{{ $event->badge_size }} portrait; margin:0; }
        @media print { body{background:white;print-color-adjust:exact;-webkit-print-color-adjust:exact}.toolbar,.settings,.notice{display:none}.grid{display:block;padding:0}.badge{border:0;border-radius:0;margin:0} }
        @media(max-width:760px){.toolbar{align-items:flex-start;flex-direction:column}.settings-grid{grid-template-columns:1fr}.grid{padding:12px;overflow:auto}}
    </style>
</head>
<body>
    <div class="toolbar">
        <div><strong>{{ $event->title }} &middot; {{ $registrations->count() }} badge(s)</strong><small>{{ $event->badge_size }} &middot; Print at 100% with background graphics enabled.</small></div>
        <div class="actions"><a class="btn alt" href="{{ route('events.registrations.index', $event) }}">Back</a><button class="btn" type="button" onclick="window.print()">Print badges</button></div>
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
            @endphp
            <article class="badge {{ $event->badge_design === 'category' ? 'category-design' : '' }}" style="--category:{{ $event->badge_design === 'category' ? $color : '#0F766E' }}">
                <header class="brand">
                    @if($event->company?->logo_path)<img class="brand-logo" src="{{ Storage::url($event->company->logo_path) }}" alt="{{ $event->company->name }} logo">@else<span class="brand-initial">{{ strtoupper(substr($event->company?->name ?? 'O', 0, 1)) }}</span>@endif
                    <span class="company-name">{{ $event->company?->name ?? 'Event Organizer' }}</span>
                </header>
                <section class="event-block">@if($event->logo_path)<img class="event-logo" src="{{ Storage::url($event->logo_path) }}" alt="{{ $event->title }} logo">@endif<div><p class="event-title">{{ $event->title }}</p><p class="event-meta">{{ $event->event_date->format('j M Y') }}@if($event->end_date && !$event->end_date->equalTo($event->event_date)) &ndash; {{ $event->end_date->format('j M Y') }}@endif @if($event->location)<br>{{ $event->location }}@endif</p></div></section>
                <section class="attendee"><p class="attendee-label">Attendee</p><h1 class="name">{{ $registration->participant->name }}</h1><div class="details"><span class="pill">{{ $category }}</span>@if($registration->participant->member_id)<span class="pill">ID: {{ $registration->participant->member_id }}</span>@endif</div></section>
                <div class="qr">{!! QrCode::size(220)->margin(1)->generate('ASAH-ATTENDANCE:'.$registration->registration_code) !!}</div><p class="scan">Scan for attendance</p>
            </article>
        @empty <p class="empty">No confirmed attendees yet.</p> @endforelse
    </main>
    <script>const d=document.getElementById('design'),c=document.getElementById('category-colors');function toggle(){c.style.display=d.value==='category'?'block':'none'}d.addEventListener('change',toggle);toggle()</script>
</body></html>
