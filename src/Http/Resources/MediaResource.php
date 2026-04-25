<?php

namespace Yazilim360\MediaManager\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms a Spatie Media model into a consistent JSON structure
 * for the Vue frontend.
 *
 * @property int    $id
 * @property string $name
 * @property string $file_name
 * @property string $mime_type
 * @property int    $size
 * @property array  $custom_properties
 */
class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Determine the media type category for the frontend
        $type = match (true) {
            str_starts_with($this->mime_type, 'image/') => 'image',
            str_starts_with($this->mime_type, 'video/') => 'video',
            $this->mime_type === 'application/pdf'      => 'pdf',
            default                                     => 'file',
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

        return [
            'id'                => $this->id,
            'uuid'              => $this->uuid,
            'name'              => $this->name,
            'file_name'         => $this->file_name,
            'mime_type'         => $this->mime_type,
            'type'              => $type,
            'size'              => $this->size,
            'size_human'        => $this->humanReadableSize,
            'url'               => $this->getUrl(),
            'conversions'       => $conversions,
            'thumb_url'         => $conversions['thumb'] ?? ($type === 'image' ? $this->getUrl() : null),
            'collection'        => $this->collection_name,
            'folder_id'         => $this->custom_properties['folder_id'] ?? null,
            'folder_name'       => $this->custom_properties['folder_name'] ?? null,
            'custom_properties' => $this->custom_properties,
            'created_at'        => $this->created_at?->toISOString(),
        ];
    }
}
