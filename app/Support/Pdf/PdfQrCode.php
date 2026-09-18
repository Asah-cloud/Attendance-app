<?php

namespace App\Support\Pdf;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Storage;

final class PdfQrCode
{
    public static function dataUri(string $text, int $size = 220, int $margin = 1): string
    {
        $path = 'badge-qr-cache/'.hash('sha256', $text."\0{$size}\0{$margin}").'.png';
        if (Storage::disk('local')->exists($path)) {
            return 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($path));
        }

        $renderer = new ImageRenderer(new RendererStyle($size, $margin), new GdQrCodeImageBackEnd);
        $png = (new Writer($renderer))->writeString($text);
        Storage::disk('local')->put($path, $png);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
