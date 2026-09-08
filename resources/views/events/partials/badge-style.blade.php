@if(!($pdfMode ?? false))
@foreach(['DejaVu Sans' => 'sans', 'DejaVu Serif' => 'serif', 'DejaVu Sans Mono' => 'mono'] as $family => $file)
@foreach(['normal' => '', 'bold' => '-Bold'] as $weight => $suffix)
@font-face{font-family:'{{ $family }}';font-weight:{{ $weight }};src:url('{{ route('events.badges.font', [$event, $file.$suffix]) }}') format('truetype')}
@endforeach
@endforeach
@endif
.badge{position:relative;overflow:hidden;box-sizing:border-box;background:#fff;color:#142c36;line-height:1.2;text-align:left}
.badge *{box-sizing:border-box}
.badge-stripe{position:absolute;left:0;top:0;width:100%;height:3%;background:#0f766e}
.badge-wash{position:absolute;left:0;top:46%;width:100%;height:27%;background:#f0f5f5}
.badge-art{position:absolute;overflow:hidden}
.badge-art img{position:absolute;display:block;max-width:none}
.layout-minimal .badge-wash,.layout-background .badge-wash,.layout-background .badge-stripe{display:none}
.badge-field{position:absolute;margin:0;padding:0;overflow:hidden;line-height:1.2;word-wrap:break-word;font-weight:normal}
.badge-field[data-field=name],.badge-field[data-field=event],.badge-field[data-field=company],.badge-field[data-field=category]{font-weight:bold}
.badge-field[data-field=category]{padding:1mm 2mm;color:#fff}
.badge-field[data-field=qr]{background:#fff;text-align:center}
.badge-field img{display:block}
.badge-field .field-content{margin:0;padding:0}
