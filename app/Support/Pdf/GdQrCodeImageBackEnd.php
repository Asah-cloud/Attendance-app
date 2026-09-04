<?php

namespace App\Support\Pdf;

use BaconQrCode\Renderer\Color\Alpha;
use BaconQrCode\Renderer\Color\ColorInterface;
use BaconQrCode\Renderer\Image\ImageBackEndInterface;
use BaconQrCode\Renderer\Image\TransformationMatrix;
use BaconQrCode\Renderer\Path\Close;
use BaconQrCode\Renderer\Path\Curve;
use BaconQrCode\Renderer\Path\EllipticArc;
use BaconQrCode\Renderer\Path\Line;
use BaconQrCode\Renderer\Path\Move;
use BaconQrCode\Renderer\Path\Path;
use BaconQrCode\Renderer\RendererStyle\Gradient;
use GdImage;

/**
 * Rasterizes BaconQrCode paths with GD instead of Imagick, which this app's
 * hosting does not have installed. dompdf cannot render the library's inline
 * SVG output, so PDF badge QR codes need a raster image.
 *
 * The renderer emits the whole QR pattern as one fill-rule="evenodd" path
 * (subpaths punch holes in each other), so subpaths must be rasterized
 * together with a scanline even-odd fill rather than each as its own solid
 * polygon.
 */
final class GdQrCodeImageBackEnd implements ImageBackEndInterface
{
    private ?GdImage $image = null;

    /** @var TransformationMatrix[] */
    private array $matrices = [];

    private int $matrixIndex = 0;

    public function new(int $size, ColorInterface $backgroundColor): void
    {
        $this->image = imagecreatetruecolor($size, $size);
        imagefilledrectangle($this->image, 0, 0, $size, $size, $this->allocate($backgroundColor));
        $this->matrices = [new TransformationMatrix];
        $this->matrixIndex = 0;
    }

    public function scale(float $size): void
    {
        $this->matrices[$this->matrixIndex] = $this->matrices[$this->matrixIndex]
            ->multiply(TransformationMatrix::scale($size));
    }

    public function translate(float $x, float $y): void
    {
        $this->matrices[$this->matrixIndex] = $this->matrices[$this->matrixIndex]
            ->multiply(TransformationMatrix::translate($x, $y));
    }

    public function rotate(int $degrees): void
    {
        $this->matrices[$this->matrixIndex] = $this->matrices[$this->matrixIndex]
            ->multiply(TransformationMatrix::rotate($degrees));
    }

    public function push(): void
    {
        $this->matrices[$this->matrixIndex + 1] = $this->matrices[$this->matrixIndex];
        $this->matrixIndex++;
    }

    public function pop(): void
    {
        unset($this->matrices[$this->matrixIndex]);
        $this->matrixIndex--;
    }

    public function drawPathWithColor(Path $path, ColorInterface $color): void
    {
        $matrix = $this->matrices[$this->matrixIndex];
        $subpaths = [];
        $current = [];

        foreach ($path as $op) {
            if ($op instanceof Move) {
                if (count($current) >= 3) {
                    $subpaths[] = $current;
                }
                $current = [$matrix->apply($op->getX(), $op->getY())];
            } elseif ($op instanceof Line || $op instanceof Curve || $op instanceof EllipticArc) {
                $current[] = $matrix->apply($op->getX(), $op->getY());
            } elseif ($op instanceof Close) {
                if (count($current) >= 3) {
                    $subpaths[] = $current;
                }
                $current = [];
            }
        }

        if (count($current) >= 3) {
            $subpaths[] = $current;
        }

        $this->fillEvenOdd($subpaths, $this->allocate($color));
    }

    public function drawPathWithGradient(
        Path $path,
        Gradient $gradient,
        float $x,
        float $y,
        float $width,
        float $height
    ): void {
        $this->drawPathWithColor($path, $gradient->getStartColor());
    }

    public function done(): string
    {
        ob_start();
        imagepng($this->image);
        $blob = ob_get_clean();
        imagedestroy($this->image);
        $this->image = null;

        return $blob;
    }

    /**
     * @param  array<int, array<int, float[]>>  $subpaths
     */
    private function fillEvenOdd(array $subpaths, int $gdColor): void
    {
        if (! $subpaths) {
            return;
        }

        $edges = [];
        $minY = PHP_FLOAT_MAX;
        $maxY = -PHP_FLOAT_MAX;

        foreach ($subpaths as $points) {
            $count = count($points);
            for ($i = 0; $i < $count; $i++) {
                [$x1, $y1] = $points[$i];
                [$x2, $y2] = $points[($i + 1) % $count];

                if ($y1 === $y2) {
                    continue;
                }

                $edges[] = [$x1, $y1, $x2, $y2];
                $minY = min($minY, $y1, $y2);
                $maxY = max($maxY, $y1, $y2);
            }
        }

        if (! $edges) {
            return;
        }

        for ($y = (int) floor($minY); $y <= (int) ceil($maxY); $y++) {
            $scanY = $y + 0.5;
            $xs = [];

            foreach ($edges as [$x1, $y1, $x2, $y2]) {
                $edgeMinY = min($y1, $y2);
                $edgeMaxY = max($y1, $y2);

                if ($scanY < $edgeMinY || $scanY >= $edgeMaxY) {
                    continue;
                }

                $xs[] = $x1 + ($scanY - $y1) / ($y2 - $y1) * ($x2 - $x1);
            }

            sort($xs);

            for ($i = 0, $total = count($xs); $i + 1 < $total; $i += 2) {
                $xStart = (int) round($xs[$i]);
                $xEnd = (int) round($xs[$i + 1]) - 1;

                if ($xEnd >= $xStart) {
                    imageline($this->image, $xStart, $y, $xEnd, $y, $gdColor);
                }
            }
        }
    }

    private function allocate(ColorInterface $color): int
    {
        $alpha = 100;

        if ($color instanceof Alpha) {
            $alpha = $color->getAlpha();
            $color = $color->getBaseColor();
        }

        $rgb = $color->toRgb();

        return imagecolorallocatealpha(
            $this->image,
            $rgb->getRed(),
            $rgb->getGreen(),
            $rgb->getBlue(),
            (int) round((1 - $alpha / 100) * 127)
        );
    }
}
