<?php

namespace Yazilim360\MediaManager\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The root model that owns all media uploaded through the media manager.
 * Uses Spatie's InteractsWithMedia to define conversions and collections.
 *
 * A single "singleton" instance (id=1) is used to group all uploaded files.
 * Each file is differentiated by `folder_name` in custom_properties.
 */
class MediaManager extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'media_manager_owners';

    protected $fillable = ['name'];

    /**
     * Register image conversions.
     * These are applied automatically when an image is added to an
     * image-based collection.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $conversions = config('media-manager.conversions', [
            'thumb'  => [300, 300],
            'medium' => [600, 600],
            'large'  => [1200, 1200],
        ]);

        foreach ($conversions as $name => [$width, $height]) {
            $this->addMediaConversion($name)
                ->width($width)
                ->height($height)
                ->sharpen(10)
                ->optimize()
                ->nonQueued(); // Use queued() in production
        }

        // Additionally generate a WebP version of originals
        $this->addMediaConversion('webp')
            ->format('webp')
            ->optimize()
            ->nonQueued();
    }

    /**
     * Register media collections.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('default')
            ->useDisk(config('media-manager.disk', 'public'));

        $this->addMediaCollection('images')
            ->useDisk(config('media-manager.disk', 'public'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml']);

        $this->addMediaCollection('videos')
            ->useDisk(config('media-manager.disk', 'public'))
            ->acceptsMimeTypes(['video/mp4', 'video/quicktime', 'video/avi', 'video/webm']);

        $this->addMediaCollection('documents')
            ->useDisk(config('media-manager.disk', 'public'))
            ->acceptsMimeTypes(['application/pdf', 'text/plain', 'application/zip']);
    }

    /**
     * Get or create the singleton owner record.
     * All media uploaded via the manager belongs to this single model instance.
     */
    public static function getSingleton(): static
    {
        return static::firstOrCreate(['name' => 'media-manager-root']);
    }
}
