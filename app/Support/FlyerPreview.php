<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * A small JPEG copy of an uploaded flyer, for link previews and the public page.
 *
 * WhatsApp shows no picture in a link preview when the image is more than a few
 * hundred KB, and a multi-megabyte flyer is slow on mobile data. The copy is made
 * once (on upload, or the first time it is asked for) and stored next to the
 * original under "previews/"; the original stays for "open full size".
 */
class FlyerPreview
{
    /** Longest side of the copy, in pixels. */
    private const MAX_SIDE = 1200;

    /** Keep the copy under this size so WhatsApp will show it. */
    private const TARGET_BYTES = 290 * 1024;

    /** Images larger than this many pixels are not decoded (would need too much memory). */
    private const MAX_PIXELS = 50_000_000;

    public static function pathFor(string $original): string
    {
        return trim(dirname($original), '/.').'/previews/'.pathinfo($original, PATHINFO_FILENAME).'.jpg';
    }

    /** URL of the small copy, made now if missing; the original's URL when a copy can't be made. */
    public static function url(string $original): string
    {
        return Storage::url(static::make($original) ?? $original);
    }

    /** Make the small copy if it doesn't exist yet. Returns its path, or null when the original can't be read. */
    public static function make(string $original): ?string
    {
        $disk = Storage::disk('public');
        $preview = static::pathFor($original);
        if ($disk->exists($preview)) {
            return $preview;
        }
        if (! $disk->exists($original) || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $info = @getimagesize($disk->path($original));
            if (! $info || $info[0] * $info[1] > self::MAX_PIXELS) {
                return null;
            }
            // Already small enough: link to it as it is, no copy needed.
            if ($disk->size($original) <= self::TARGET_BYTES && max($info[0], $info[1]) <= self::MAX_SIDE * 2) {
                return $original;
            }

            $limit = ini_get('memory_limit');
            ini_set('memory_limit', '512M');
            try {
                $jpeg = static::encode($disk->get($original));
            } finally {
                ini_set('memory_limit', $limit);
            }
            if ($jpeg === null) {
                return null;
            }

            $disk->put($preview, $jpeg);

            return $preview;
        } catch (Throwable $exception) {
            Log::warning('Could not make a flyer preview', ['path' => $original, 'error' => $exception->getMessage()]);

            return null;
        }
    }

    public static function delete(string $original): void
    {
        Storage::disk('public')->delete(static::pathFor($original));
    }

    /** Resize to MAX_SIDE on a white background (for transparent PNGs) and lower the quality until it fits TARGET_BYTES. */
    private static function encode(string $bytes): ?string
    {
        $source = @imagecreatefromstring($bytes);
        if (! $source) {
            return null;
        }

        $jpeg = null;
        foreach ([self::MAX_SIDE, 1000, 800] as $side) {
            [$width, $height] = [imagesx($source), imagesy($source)];
            $scale = min(1, $side / max($width, $height));
            [$newWidth, $newHeight] = [max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale))];

            $canvas = imagecreatetruecolor($newWidth, $newHeight);
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            foreach ([82, 72, 62] as $quality) {
                ob_start();
                imagejpeg($canvas, null, $quality);
                $jpeg = (string) ob_get_clean();
                if (strlen($jpeg) <= self::TARGET_BYTES) {

                    return $jpeg;
                }
            }
        }

        // Even the smallest attempt is over the target: still far smaller than the original.
        return $jpeg;
    }
}
