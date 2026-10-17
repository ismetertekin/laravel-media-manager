<?php

namespace Yazilim360\MediaManager\Support;

use GdImage;
use RuntimeException;

/**
 * Small GD based editing pipeline. The order is part of the public contract
 * because the browser crop editor renders the same steps:
 *
 *   EXIF orientation → flipX/flipY → rotate (clockwise, 90° steps) → crop → resize
 *
 * Crop coordinates are always in pixels of the oriented/flipped/rotated image.
 */
class ImageEditor
{
    public const MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public static function supports(?string $mime): bool
    {
        return in_array($mime, self::MIMES, true);
    }

    /**
     * Width/height of the image as a viewer displays it (EXIF orientation applied),
     * without decoding the pixels.
     *
     * @return array{0:int,1:int}|null
     */
    public function dimensions(string $path, ?string $mime = null): ?array
    {
        $info = @getimagesize($path);
        if (! $info) {
            return null;
        }

        [$w, $h] = $info;
        $orientation = $this->exifOrientation($path, $info['mime'] ?? $mime);

        return in_array($orientation, [5, 6, 7, 8], true) ? [$h, $w] : [$w, $h];
    }

    public function load(string $path): GdImage
    {
        $info = @getimagesize($path);
        if (! $info) {
            throw new RuntimeException('Unreadable image.');
        }

        [$w, $h] = $info;
        $this->guardMemory($w, $h);

        $img = match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };

        if (! $img) {
            throw new RuntimeException('Unsupported or corrupt image.');
        }

        imagepalettetotruecolor($img);

        return $this->applyExifOrientation($img, $this->exifOrientation($path, $info['mime']));
    }

    public function transform(GdImage $img, bool $flipX = false, bool $flipY = false, int $rotate = 0): GdImage
    {
        if ($flipX) {
            imageflip($img, IMG_FLIP_HORIZONTAL);
        }
        if ($flipY) {
            imageflip($img, IMG_FLIP_VERTICAL);
        }

        $rotate = (($rotate % 360) + 360) % 360;
        if ($rotate === 0) {
            return $img;
        }

        // imagerotate() is counter-clockwise
        return $this->rotate($img, -$rotate);
    }

    /**
     * @param  array{x:int,y:int,width:int,height:int}  $region
     */
    public function cropResize(GdImage $img, array $region, int $targetW, int $targetH): GdImage
    {
        $out = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));

        imagecopyresampled(
            $out, $img, 0, 0,
            $region['x'], $region['y'], $targetW, $targetH,
            $region['width'], $region['height']
        );

        return $out;
    }

    /**
     * Largest centred region of the given ratio.
     *
     * @return array{x:int,y:int,width:int,height:int}
     */
    public static function centeredRegion(int $imgW, int $imgH, int $ratioW, int $ratioH): array
    {
        $w = $imgW;
        $h = (int) round($w * $ratioH / $ratioW);

        if ($h > $imgH) {
            $h = $imgH;
            $w = (int) round($h * $ratioW / $ratioH);
        }

        $w = max(1, min($w, $imgW));
        $h = max(1, min($h, $imgH));

        return [
            'x' => intdiv($imgW - $w, 2),
            'y' => intdiv($imgH - $h, 2),
            'width' => $w,
            'height' => $h,
        ];
    }

    /**
     * Clamp a client supplied region into the image bounds.
     *
     * @return array{x:int,y:int,width:int,height:int}
     */
    public static function clampRegion(array $region, int $imgW, int $imgH): array
    {
        $w = max(1, min((int) round($region['width']), $imgW));
        $h = max(1, min((int) round($region['height']), $imgH));
        $x = max(0, min((int) round($region['x']), $imgW - $w));
        $y = max(0, min((int) round($region['y']), $imgH - $h));

        return ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h];
    }

    public function save(GdImage $img, string $path, string $mime): void
    {
        $quality = (int) config('media-manager.image_quality', 85);

        $ok = match ($mime) {
            'image/jpeg' => $this->saveJpeg($img, $path, $quality),
            'image/png' => $this->savePng($img, $path),
            'image/gif' => imagegif($img, $path),
            'image/webp' => imagewebp($img, $path, $quality),
            default => false,
        };

        if (! $ok) {
            throw new RuntimeException("Could not write {$mime} image.");
        }
    }

    public static function extensionFor(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'bin',
        };
    }

    protected function saveJpeg(GdImage $img, string $path, int $quality): bool
    {
        // JPEG has no alpha: flatten on white.
        $w = imagesx($img);
        $h = imagesy($img);
        $flat = imagecreatetruecolor($w, $h);
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $img, 0, 0, 0, 0, $w, $h);

        return imagejpeg($flat, $path, $quality);
    }

    protected function savePng(GdImage $img, string $path): bool
    {
        imagealphablending($img, false);
        imagesavealpha($img, true);

        return imagepng($img, $path, 6);
    }

    protected function rotate(GdImage $img, int $angle): GdImage
    {
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        $rotated = imagerotate($img, $angle, $transparent);

        if (! $rotated) {
            throw new RuntimeException('Rotation failed.');
        }

        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);

        return $rotated;
    }

    protected function exifOrientation(string $path, ?string $mime): int
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data($path);

        return (int) ($exif['Orientation'] ?? 1);
    }

    protected function applyExifOrientation(GdImage $img, int $orientation): GdImage
    {
        return match ($orientation) {
            2 => $this->transform($img, flipX: true),
            3 => $this->transform($img, rotate: 180),
            4 => $this->transform($img, flipY: true),
            5 => $this->transform($img, flipX: true, rotate: 270),
            6 => $this->transform($img, rotate: 90),
            7 => $this->transform($img, flipX: true, rotate: 90),
            8 => $this->transform($img, rotate: 270),
            default => $img,
        };
    }

    protected function guardMemory(int $w, int $h): void
    {
        $limit = (int) config('media-manager.max_dimension', 8000);
        if ($w > $limit || $h > $limit) {
            throw new RuntimeException("Image is too large to process ({$w}x{$h}).");
        }

        $needed = (int) ($w * $h * 4 * 3);
        $limitBytes = $this->memoryLimitBytes();

        if ($limitBytes !== -1 && $needed + memory_get_usage(true) > $limitBytes) {
            @ini_set('memory_limit', (string) max($limitBytes, $needed + memory_get_usage(true) + 64 * 1024 * 1024));
        }
    }

    protected function memoryLimitBytes(): int
    {
        $raw = trim((string) ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1') {
            return -1;
        }

        $value = (int) $raw;

        return match (strtolower(substr($raw, -1))) {
            'g' => $value * 1024 ** 3,
            'm' => $value * 1024 ** 2,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
