<?php

namespace Yazilim360\MediaManager\Support;

/**
 * The subset of config/media-manager.php that is safe to expose to the browser.
 */
class ClientConfig
{
    public static function toArray(): array
    {
        $allowed = (array) config('media-manager.allowed_types', []);
        if (! config('media-manager.allow_svg')) {
            $allowed = array_values(array_diff($allowed, ['image/svg+xml']));
        }

        return [
            'route_prefix' => config('media-manager.route_prefix', 'media-manager'),
            'sidebar' => (bool) config('media-manager.sidebar', false),
            'default_view' => config('media-manager.default_view', 'grid'),
            'default_theme' => config('media-manager.default_theme', 'auto'),
            'allowed_locales' => config('media-manager.allowed_locales', ['en', 'tr']),
            'per_page' => (int) config('media-manager.per_page', 30),
            'max_upload' => UploadLimits::effectiveKb(),
            'allowed_types' => $allowed,
            'default_variants' => array_values((array) config('media-manager.default_variants', [])),
            'video_thumb_second' => (float) config('media-manager.video_thumb_second', 1),
            'video_poster_width' => (int) config('media-manager.video_poster_width', 640),
            'client_poster' => (bool) config('media-manager.client_poster', true),
        ];
    }

    /** Translations for a locale, always an array. */
    public static function translations(?string $locale = null): array
    {
        $lines = trans('media-manager::media-manager', [], $locale ?? app()->getLocale());

        return is_array($lines) ? $lines : [];
    }

    /** Public URL of a published asset, with a cache-busting version; null if not published. */
    public static function asset(string $file): ?string
    {
        $path = public_path("vendor/media-manager/{$file}");

        return is_file($path) ? asset("vendor/media-manager/{$file}").'?v='.filemtime($path) : null;
    }
}
