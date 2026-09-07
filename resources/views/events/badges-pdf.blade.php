<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><style>
@page{margin:0}body{margin:0;padding:0}
@include('events.partials.badge-style', ['pdfMode' => true])
.sheet{position:relative;page-break-after:always}.sheet:last-child{page-break-after:auto}
.slot{position:absolute}.cut-guide{position:absolute;border:0.2mm dashed #64748b}
</style></head><body>
@php
    [$w, $h] = \App\Support\BadgeDesign::dimensions($event);
    $onSheet = ($paper ?? 'badge') === 'a4';
    $perPage = $onSheet && $event->badge_size !== 'A5' ? 2 : 1;
    $sheetW = $onSheet ? ($perPage === 2 ? 297 : 210) : $w;
    $sheetH = $onSheet ? ($perPage === 2 ? 210 : 297) : $h;
    $gap = $perPage === 2 ? 10 : 0;
    $startX = ($sheetW - ($w * $perPage + $gap)) / 2;
    $startY = ($sheetH - $h) / 2;
@endphp
@foreach($registrations->chunk($perPage) as $batch)
<div class="sheet" style="width:{{ $sheetW }}mm;height:{{ $sheetH - 0.2 }}mm">
    @foreach($batch->values() as $index => $registration)
        <div class="slot" style="left:{{ $startX + $index * ($w + $gap) }}mm;top:{{ $startY }}mm">
        @include('events.partials.badge', ['pdfMode' => true])
        </div>
        @if($onSheet && ($cutGuides ?? false))<div class="cut-guide" style="left:{{ $startX + $index * ($w + $gap) - 0.5 }}mm;top:{{ $startY - 0.5 }}mm;width:{{ $w + 1 }}mm;height:{{ $h + 1 }}mm"></div>@endif
    @endforeach
</div>
@endforeach
</body></html>
