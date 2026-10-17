<?php

namespace Yazilim360\MediaManager\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Renders theme specific image variants from the untouched original.
 *
 * Stored in custom_properties:
 *   variant_specs : the requested specs (so the UI can re-open the crop editor)
 *   crops         : last crop per ratio key
 *   variants      : name → {file, width, height, ratio}
 *   variants_status: ready | pending | failed
 */
class VariantGenerator
{
    public function __construct(
        protected ImageEditor $editor = new ImageEditor,
        protected MediaAnalyzer $analyzer = new MediaAnalyzer,
    ) {}

    /**
     * @param  list<VariantSpec>  $specs
     * @param  array<string, array>  $crops  ratioKey → {x,y,width,height,rotate,flipX,flipY}
     */
    public function generate(Media $media, array $specs, array $crops = []): Media
    {
        if (! ImageEditor::supports($media->mime_type)) {
            return $media;
        }

        $media->refresh();
        $existing = (array) $media->getCustomProperty('variants', []);
        $storedCrops = (array) $media->getCustomProperty('crops', []);
        $crops = array_replace($storedCrops, $this->sanitizeCrops($crops));

        $result = $this->analyzer->withLocalCopy($media, function (string $source) use ($media, $specs, $crops, $existing) {
            $original = $this->editor->load($source);
            $cache = [];
            $variants = $existing;

            foreach ($specs as $spec) {
                $edit = $crops[$spec->ratioKey] ?? [];
                $editKey = md5(json_encode([$edit['rotate'] ?? 0, $edit['flipX'] ?? false, $edit['flipY'] ?? false]));

                $cache[$editKey] ??= $this->editor->transform(
                    $this->copy($original),
                    (bool) ($edit['flipX'] ?? false),
                    (bool) ($edit['flipY'] ?? false),
                    (int) ($edit['rotate'] ?? 0),
                );

                $transformed = $cache[$editKey];
                $iw = imagesx($transformed);
                $ih = imagesy($transformed);

                $region = isset($edit['width'], $edit['height'], $edit['x'], $edit['y'])
                    ? ImageEditor::clampRegion($edit, $iw, $ih)
                    : ImageEditor::centeredRegion($iw, $ih, $spec->ratioW, $spec->ratioH);

                [$tw, $th] = $this->targetSize($spec, $region);

                $out = $this->editor->cropResize($transformed, $region, $tw, $th);

                $this->removeFile($media, $variants[$spec->name]['file'] ?? null);

                $file = $this->fileName($media, $tw, $th);
                $this->write($media, $file, $out, $media->mime_type);

                $variants[$spec->name] = [
                    'file' => $file,
                    'width' => $tw,
                    'height' => $th,
                    'ratio' => $spec->ratioKey,
                ];
            }

            return $variants;
        });

        $media->setCustomProperty('variants', $result);
        $media->setCustomProperty('variants_status', 'ready');
        $media->setCustomProperty('variant_specs', $this->mergeSpecs((array) $media->getCustomProperty('variant_specs', []), $specs));
        $media->setCustomProperty('crops', $crops);
        $media->save();

        return $media;
    }

    public function deleteAll(Media $media): void
    {
        foreach ((array) $media->getCustomProperty('variants', []) as $variant) {
            $this->removeFile($media, $variant['file'] ?? null);
        }

        $media->forgetCustomProperty('variants');
        $media->forgetCustomProperty('crops');
        $media->forgetCustomProperty('variant_specs');
        $media->forgetCustomProperty('variants_status');
        $media->save();
    }

    /** Directory (relative to the disk root) that holds the variant files. */
    public function directory(Media $media): string
    {
        return rtrim(dirname($media->getPathRelativeToRoot()), '/').'/variants';
    }

    public function url(Media $media, string $file): string
    {
        return rtrim(dirname($media->getUrl()), '/').'/variants/'.rawurlencode($file);
    }

    /** @return array{0:int,1:int} */
    protected function targetSize(VariantSpec $spec, array $region): array
    {
        if ($spec->width && $spec->height) {
            return [$spec->width, $spec->height];
        }

        // Ratio only → keep the crop's native resolution.
        $w = $region['width'];

        return [$w, max(1, (int) round($w * $spec->ratioH / $spec->ratioW))];
    }

    protected function fileName(Media $media, int $w, int $h): string
    {
        $base = pathinfo($media->file_name, PATHINFO_FILENAME);
        $ext = pathinfo($media->file_name, PATHINFO_EXTENSION) ?: ImageEditor::extensionFor($media->mime_type);

        return "{$base}-{$w}x{$h}.{$ext}";
    }

    protected function write(Media $media, string $file, \GdImage $img, string $mime): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mmv_');

        try {
            $this->editor->save($img, $tmp, $mime);
            $stream = fopen($tmp, 'r');
            if ($stream === false) {
                throw new RuntimeException('Could not read generated variant.');
            }
            Storage::disk($media->disk)->put($this->directory($media).'/'.$file, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        } finally {
            @unlink($tmp);
        }
    }

    protected function removeFile(Media $media, ?string $file): void
    {
        if ($file) {
            Storage::disk($media->disk)->delete($this->directory($media).'/'.$file);
        }
    }

    protected function copy(\GdImage $img): \GdImage
    {
        $copy = imagecreatetruecolor(imagesx($img), imagesy($img));
        imagealphablending($copy, false);
        imagesavealpha($copy, true);
        imagecopy($copy, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));

        return $copy;
    }

    /** @return array<string, array> */
    protected function sanitizeCrops(array $crops): array
    {
        $clean = [];

        foreach ($crops as $ratioKey => $c) {
            if (! is_array($c) || ! preg_match('/^\d+:\d+$/', (string) $ratioKey)) {
                continue;
            }

            $clean[$ratioKey] = [
                'x' => (int) round($c['x'] ?? 0),
                'y' => (int) round($c['y'] ?? 0),
                'width' => max(1, (int) round($c['width'] ?? 1)),
                'height' => max(1, (int) round($c['height'] ?? 1)),
                'rotate' => ((int) round(((int) ($c['rotate'] ?? 0)) / 90) * 90 % 360 + 360) % 360,
                'flipX' => (bool) ($c['flipX'] ?? false),
                'flipY' => (bool) ($c['flipY'] ?? false),
            ];
        }

        return $clean;
    }

    /** @param list<VariantSpec> $specs */
    protected function mergeSpecs(array $stored, array $specs): array
    {
        $byName = [];
        foreach ($stored as $row) {
            if (isset($row['name'])) {
                $byName[$row['name']] = $row;
            }
        }
        foreach ($specs as $spec) {
            $byName[$spec->name] = $spec->toArray();
        }

        return array_values($byName);
    }
}
