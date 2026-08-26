<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Helvetica, Arial, sans-serif; color: #0f172a; }
        .page {
            position: relative;
            width: 100%;
            height: {{ $event->badge_size === 'A5' ? '210mm' : '148mm' }};
            background-color: #faf9f5;
            @if(($event->badge_layout ?? 'standard') === 'standard' && $backgroundImage)
                background-image: url('{{ $backgroundImage }}');
                background-size: cover;
                background-position: center;
            @endif
        }
        .top { position: absolute; top: 6mm; left: 6mm; right: 6mm; }
        .bottom { position: absolute; left: 6mm; right: 6mm; bottom: 6mm; }
        table.row { width: 100%; border-collapse: collapse; }
        .box { border: 1px solid rgba(148,163,184,.8); border-radius: 4mm; background: rgba(255,255,255,.92); }
        .brand-box { padding: 2.5mm 3mm; }
        .brand-initial { width: 11mm; height: 11mm; border-radius: 50%; background: #ccfbf1; color: #134e4a; font-size: 4.5mm; font-weight: bold; text-align: center; }
        .brand-initial div { line-height: 11mm; }
        .company-name { font-size: 3.8mm; font-weight: bold; letter-spacing: .02em; padding-left: 3mm; }
        .event-block { margin-top: 5mm; }
        .event-title { margin: 0; font-size: 5.4mm; font-weight: bold; color: #0f172a; }
        .event-meta { margin: 1.5mm 0 0; color: #475569; font-size: 2.9mm; font-weight: bold; }
        .attendee-box { margin-top: 5mm; border-radius: 4mm; background: rgba(255,255,255,.94); padding: 5mm; }
        .attendee-label { margin: 0 0 2mm; font-size: 2.8mm; font-weight: bold; letter-spacing: .2em; text-transform: uppercase; }
        .name { margin: 0; font-size: 8mm; line-height: 1.1; font-weight: bold; color: #0f172a; }
        .category-pill { display: inline-block; margin-top: 2.5mm; border-radius: 999px; padding: 1.3mm 4mm; color: white; font-size: 3mm; font-weight: bold; }
        .credential-box { padding: 4mm; }
        .credential-label { margin: 0 0 1.5mm; color: #64748b; font-size: 2.5mm; font-weight: bold; letter-spacing: .16em; text-transform: uppercase; }
        .member-id { margin: 0; color: #0f172a; font-size: 4mm; font-weight: bold; }
        .scan { margin: 3mm 0 0; color: #475569; font-size: 2.5mm; font-weight: bold; }
        .qr-box { border-radius: 2.5mm; background: white; padding: 2mm; text-align: center; }
        .custom-art { height: 30mm; border-radius: 4mm; background-size: cover; background-repeat: no-repeat; margin-bottom: 3mm; }
        .custom-brand { min-height: 14mm; padding: 2mm 3mm; }
        .custom-event { margin-top: 2.5mm; }
        .split-art { width: 33mm; height: 43mm; border-radius: 4mm; background-size: cover; background-repeat: no-repeat; }
        .split-copy { padding-left: 3mm; vertical-align: top; }
        .custom-attendee { margin-top: 3mm; padding: 4mm; }
    </style>
</head>
<body>
@forelse($registrations as $registration)
    @php
        $category = $registration->participant->category ?: 'Attendee';
        $color = $categoryColors[$category] ?? '#0F766E';
        $categoryColor = $event->badge_design === 'category' ? $color : ($event->badge_primary_color ?? '#0F766E');
        $accentColor = $event->badge_accent_color ?? '#0F172A';
        $layout = $event->badge_layout ?? 'standard';
        $nameParts = preg_split('/\s+/u', trim($registration->participant->name), -1, PREG_SPLIT_NO_EMPTY);
        if (count($nameParts) >= 3) {
            $middleInitials = array_map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)).'.', array_slice($nameParts, 1, -1));
            $badgeName = implode(' ', [$nameParts[0], ...$middleInitials, $nameParts[array_key_last($nameParts)]]);
        } else {
            $badgeName = $registration->participant->name;
        }
        $hasMemberId = filled($registration->participant->member_id);
        $qrDataUri = \App\Support\Pdf\PdfQrCode::dataUri('ASAH-ATTENDANCE:'.$registration->registration_code);
    @endphp
    <div class="page"@if(!$loop->last) style="page-break-after: always;"@endif>
        <div class="top">
            @if($layout === 'image_header' && $badgeImage)
                <div class="custom-art" style="background-image:url('{{ $badgeImage }}');background-position:{{ $event->badge_image_position_x ?? 50 }}% {{ $event->badge_image_position_y ?? 50 }}%;"></div>
            @elseif($layout === 'split' && $badgeImage)
                <table class="row"><tr><td style="width:33mm;vertical-align:top"><div class="split-art" style="background-image:url('{{ $badgeImage }}');background-position:{{ $event->badge_image_position_x ?? 50 }}% {{ $event->badge_image_position_y ?? 50 }}%;"></div></td><td class="split-copy">
            @endif
            <div class="box brand-box">
                <table class="row"><tr>
                    <td style="width: 12mm; vertical-align: middle;">
                        @if($companyLogo)
                            <img src="{{ $companyLogo }}" style="width: 11mm; height: 11mm;">
                        @else
                            <div class="brand-initial"><div>{{ strtoupper(substr($event->company?->name ?? 'O', 0, 1)) }}</div></div>
                        @endif
                    </td>
                    <td class="company-name" style="vertical-align: middle;">{{ $event->company?->name ?? 'Event Organizer' }}</td>
                </tr></table>
            </div>
            <div class="event-block">
                <table class="row"><tr>
                    @if($eventLogo)
                        <td style="width: 14mm; vertical-align: middle;"><img src="{{ $eventLogo }}" style="width: 12mm; height: 12mm;"></td>
                    @endif
                    <td style="vertical-align: middle;">
                        <p class="event-title">{{ $event->title }}</p>
                        <p class="event-meta">{{ $event->event_date->format('j M Y') }}@if($event->end_date && !$event->end_date->equalTo($event->event_date)) &ndash; {{ $event->end_date->format('j M Y') }}@endif @if($event->location)<br>{{ $event->location }}@endif</p>
                    </td>
                </tr></table>
            </div>
            @if($layout === 'split' && $badgeImage)</td></tr></table>@endif
            <div class="attendee-box {{ $layout === 'standard' ? '' : 'custom-attendee' }}" style="border-left: 1.5mm solid {{ $categoryColor }};">
                <p class="attendee-label" style="color: {{ $categoryColor }};">Attendee</p>
                <h1 class="name" style="color:{{ $accentColor }}">{{ $badgeName }}</h1>
                <span class="category-pill" style="background: {{ $categoryColor }};">{{ $category }}</span>
            </div>
        </div>
        <div class="bottom">
            <div class="box credential-box">
                <table class="row"><tr>
                    <td style="vertical-align: middle;">
                        <p class="credential-label">{{ $hasMemberId ? 'Member ID' : 'Credential' }}</p>
                        <p class="member-id">{{ $hasMemberId ? $registration->participant->member_id : 'Event Pass' }}</p>
                        <p class="scan">Attendance &amp; meal collection</p>
                    </td>
                    <td style="width: 26mm; vertical-align: middle;">
                        <div class="qr-box" style="border: 1.5px solid {{ $categoryColor }};"><img src="{{ $qrDataUri }}" style="width: 22mm; height: 22mm; display: block;"></div>
                    </td>
                </tr></table>
            </div>
        </div>
    </div>
@empty
    <div class="page"><div class="top"><p>No confirmed attendees yet.</p></div></div>
@endforelse
</body>
</html>
