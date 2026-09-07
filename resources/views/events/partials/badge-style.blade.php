@if(!($pdfMode ?? false))
@foreach(['DejaVu Sans' => 'DejaVuSans', 'DejaVu Serif' => 'DejaVuSerif', 'DejaVu Sans Mono' => 'DejaVuSansMono'] as $family => $file)
@foreach(['normal' => '', 'bold' => '-Bold'] as $weight => $suffix)
@font-face{font-family:'{{ $family }}';font-weight:{{ $weight }};src:url('data:font/ttf;base64,{{ base64_encode(file_get_contents(base_path("vendor/dompdf/dompdf/lib/fonts/{$file}{$suffix}.ttf"))) }}') format('truetype')}
@endforeach
@endforeach
@endif
.badge{position:relative;overflow:hidden;box-sizing:border-box;background:#fff;color:#142c36;line-height:1.2;text-align:left}
.badge *{box-sizing:border-box}
.badge-stripe{position:absolute;left:0;top:0;width:100%;height:3%;background:#0f766e}
.badge-wash{position:absolute;left:0;top:46%;width:100%;height:27%;background:#f0f5f5}
.badge-art{position:absolute;left:0;top:0;width:100%;height:100%;background-repeat:no-repeat;background-size:100% 100%}
.layout-image_header .badge-art,.layout-split .badge-art{left:7%;top:4%;width:86%;height:17%;background-size:cover}
.layout-minimal .badge-wash,.layout-background .badge-wash,.layout-background .badge-stripe{display:none}
.badge-field{position:absolute;margin:0;padding:0;overflow:hidden;line-height:1.2;word-wrap:break-word;font-weight:normal}
.badge-field[data-field=name],.badge-field[data-field=event],.badge-field[data-field=company],.badge-field[data-field=category]{font-weight:bold}
.badge-field[data-field=category]{padding:1mm 2mm;color:#fff}
.badge-field[data-field=qr]{background:#fff;text-align:center}
.badge-field img{display:block}
.badge-field .field-content{margin:0;padding:0}
