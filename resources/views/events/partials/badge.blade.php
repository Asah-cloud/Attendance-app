@php
    [$badgeWidth, $badgeHeight] = \App\Support\BadgeDesign::dimensions($event);
    $values = \App\Support\BadgeDesign::values($event, $registration);
    $primary = $event->badge_primary_color ?? '#0F766E';
    $accent = $event->badge_accent_color ?? '#0F172A';
    $categoryColor = $event->badge_design === 'category' ? ($categoryColors[$values['category']] ?? $primary) : $primary;
    $imageUrl = fn ($path) => ($pdfMode ?? false) ? \App\Support\BadgeDesign::image($path) : ($path ? Storage::url($path) : null);
    $art = $imageUrl($event->badge_image_path);
@endphp
<div class="badge layout-{{ $event->badge_layout ?? 'standard' }}" style="width:{{ $badgeWidth }}mm;height:{{ $badgeHeight }}mm;font-family:'{{ $event->badge_font ?? 'DejaVu Sans' }}';color:{{ $accent }}" @if(!($pdfMode ?? false)) data-registration="{{ $registration?->id ?? 'sample' }}" @endif>
    <div class="badge-stripe" style="background:{{ $primary }}"></div><div class="badge-wash"></div>
    <div class="badge-art" style="@if($art && in_array($event->badge_layout, ['background','image_header','split'])) background-image:url('{{ $art }}'); @endif background-position:{{ $event->badge_image_position_x ?? 50 }}% {{ $event->badge_image_position_y ?? 50 }}%"></div>
    @foreach($fields as $key => $field)
        @php $qrSize = min($field['w'] * $badgeWidth / 100, $field['h'] * $badgeHeight / 100); @endphp
        <div class="badge-field" data-field="{{ $key }}" style="left:{{ $field['x'] * $badgeWidth / 100 }}mm;top:{{ $field['y'] * $badgeHeight / 100 }}mm;width:{{ $field['w'] * $badgeWidth / 100 }}mm;height:{{ $field['h'] * $badgeHeight / 100 }}mm;font-size:{{ $field['size'] }}pt;text-align:{{ $field['align'] }};@if(!$field['visible']) display:none; @endif @if($key === 'category') background:{{ $categoryColor }}; @endif" @if(!($pdfMode ?? false)) tabindex="0" role="button" aria-label="Edit {{ \App\Support\BadgeDesign::LABELS[$key] }}" @endif>
            @if($key === 'qr')
                @if($registration)
                <img src="{{ \App\Support\Pdf\PdfQrCode::dataUri('ASAH-ATTENDANCE:'.$registration->registration_code, 400, 4) }}" alt="Attendance &amp; meal collection QR code" style="width:{{ $qrSize }}mm;height:{{ $qrSize }}mm">
                @else
                <div class="field-content" style="padding:3mm;font-size:9pt">QR code<br>Sample only</div>
                @endif
            @elseif(in_array($key, ['company_logo','event_logo']))
                @php $logo = $imageUrl($key === 'company_logo' ? $event->company?->logo_path : $event->logo_path); @endphp
                @if($logo)<img src="{{ $logo }}" alt="{{ $key === 'company_logo' ? 'Company' : 'Event' }} logo" style="max-width:100%;max-height:100%">@endif
            @else
                <div class="field-content">{{ $values[$key] ?? '' }}</div>
            @endif
        </div>
    @endforeach
</div>
