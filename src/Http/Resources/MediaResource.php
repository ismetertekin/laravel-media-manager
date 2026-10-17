<?php

namespace Yazilim360\MediaManager\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Yazilim360\MediaManager\Support\ImageEditor;
use Yazilim360\MediaManager\Support\VariantGenerator;

/**
 * Transforms a Spatie Media model into a consistent JSON structure
 * for the Vue frontend.
 *
 * @property int $id
 * @property string $name
 * @property string $file_name
 * @property string $mime_type
 * @property int $size
 * @property array $custom_properties
 */
class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $mime = (string) $this->mime_type;

        // Determine the media type category for the frontend
        $type = match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            $mime === 'application/pdf' => 'pdf',
            default => 'file',
        };

        // Build conversion URLs safely
        $conversions = [];
        foreach (['thumb', 'medium', 'large', 'webp'] as $conversion) {
            try {
                if ($this->hasGeneratedConversion($conversion)) {
                    $conversions[$conversion] = $this->getUrl($conversion);
                }
            } catch (\Exception) {
                // Conversion not available for this file type
            }
        }

        $thumbUrl = $conversions['thumb'] ?? ($type === 'image' ? $this->getUrl() : null);
        $props = (array) $this->custom_properties;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'file_name' => $this->file_name,
            'mime_type' => $mime,
            'type' => $type,
            'size' => $this->size,
            'size_human' => $this->humanReadableSize,
            'url' => $this->getUrl(),
            'conversions' => $conversions,
            'thumb_url' => $thumbUrl,
            'thumbnail_url' => $thumbUrl,
            'preview_url' => $thumbUrl,
            'collection' => $this->collection_name,
            'folder_id' => $props['folder_id'] ?? null,
            'folder_name' => $props['folder_name'] ?? null,
            'custom_properties' => $props,
            'created_at' => $this->created_at?->toISOString(),

            // Added in 2.0 (additive, existing keys above are unchanged)
            'width' => $props['width'] ?? null,
            'height' => $props['height'] ?? null,
            'duration' => $props['duration'] ?? null,
            'alt' => $props['alt'] ?? null,
            'title' => $props['title'] ?? null,
            'description' => $props['description'] ?? null,
            'variants' => $this->variants($props),
            'variants_status' => $props['variants_status'] ?? null,
            'crops' => $props['crops'] ?? (object) [],
            'variant_specs' => $props['variant_specs'] ?? [],
            'editable' => in_array($mime, ImageEditor::MIMES, true),
        ];
    }

    /**
     * name → {url, width, height, ratio}
     */
    protected function variants(array $props): array|object
    {
        $out = [];
        $generator = app(VariantGenerator::class);

        foreach ((array) ($props['variants'] ?? []) as $name => $variant) {
            if (empty($variant['file'])) {
                continue;
            }

            $out[$name] = [
                'url' => $generator->url($this->resource, $variant['file']),
                'width' => $variant['width'] ?? null,
                'height' => $variant['height'] ?? null,
                'ratio' => $variant['ratio'] ?? null,
                'file' => $variant['file'],
            ];
        }

        return $out === [] ? (object) [] : $out;
    }
}
