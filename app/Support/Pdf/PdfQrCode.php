<?php

namespace App\Support\Pdf;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

final class PdfQrCode
{
    public static function dataUri(string $text, int $size = 220, int $margin = 1): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, $margin), new GdQrCodeImageBackEnd());
        $png = (new Writer($renderer))->writeString($text);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
