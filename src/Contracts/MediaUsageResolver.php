<?php

namespace Yazilim360\MediaManager\Contracts;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Implement this in the host application to tell the media manager where a
 * media item is used (products, pages, ...). Register it with
 * `media-manager.usage_resolver`.
 */
interface MediaUsageResolver
{
    /**
     * Human readable places the media is used in, e.g. ['Product #12 (T-Shirt)'].
     * An empty array means the media can be deleted safely.
     *
     * @return list<string>
     */
    public function usages(Media $media): array;
}
