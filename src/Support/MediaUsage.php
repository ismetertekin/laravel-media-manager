<?php

namespace Yazilim360\MediaManager\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Yazilim360\MediaManager\Contracts\MediaUsageResolver;

class MediaUsage
{
    /** @return list<string> */
    public static function for(Media $media): array
    {
        $class = config('media-manager.usage_resolver');

        if (! $class) {
            return [];
        }

        $resolver = is_object($class) ? $class : app($class);

        if (! $resolver instanceof MediaUsageResolver) {
            throw new \LogicException('media-manager.usage_resolver must implement '.MediaUsageResolver::class);
        }

        return array_values($resolver->usages($media));
    }
}
