<?php

namespace Yazilim360\MediaManager\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator as BasePathGenerator;

class PathGenerator implements BasePathGenerator
{
    /**
     * Get the path for the given media, relative to the root storage path.
     *
     * Resulting path: {disk_path}/{folder_slugs}/{media_id}/
     */
    public function getPath(Media $media): string
    {
        $base = trim(config('media-manager.disk_path', 'media-manager'), '/');
        $folderId = $media->getCustomProperty('folder_id');

        $folderSegment = '';
        if ($folderId && ($folder = \Yazilim360\MediaManager\Models\Folder::find($folderId))) {
            $folderSegment = $folder->nestedSlugPath() . '/';
        }

        return $base . '/' . $folderSegment . $media->id . '/';
    }

    /**
     * Get the path for conversions of the given media, relative to the root storage path.
     */
    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media) . 'conversions/';
    }

    /**
     * Get the path for responsive images of the given media, relative to the root storage path.
     */
    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media) . 'responsive-images/';
    }
}
