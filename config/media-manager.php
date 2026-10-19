<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | route_middleware protects every route (page + JSON API). The default
    | requires an authenticated user. For apps with several guards use the
    | guard-specific middleware, e.g. MEDIA_MANAGER_MIDDLEWARE="web,auth:admin".
    |
    | authorize (optional): a Gate ability name. When set, the current user must
    | pass Gate::allows($ability) on every request, e.g. 'manage-media'.
    |
    */
    'route_prefix' => env('MEDIA_MANAGER_ROUTE_PREFIX', 'media-manager'),
    'route_middleware' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MEDIA_MANAGER_MIDDLEWARE', 'web,auth'))
    ))),
    'authorize' => env('MEDIA_MANAGER_GATE'),

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    */
    'disk' => env('MEDIA_MANAGER_DISK', 'public'),
    'disk_path' => env('MEDIA_MANAGER_DISK_PATH', 'media-manager'),

    /*
    |--------------------------------------------------------------------------
    | Upload Constraints
    |--------------------------------------------------------------------------
    |
    | max_upload is in kilobytes. It drives Laravel request validation and is
    | automatically synced to Spatie's media-library.max_file_size (bytes).
    | Also ensure PHP post_max_size and upload_max_filesize exceed this value.
    |
    | SVG files can carry scripts and are served from your own origin, so they
    | are rejected unless allow_svg is true (and then only "clean" SVGs pass).
    |
    */
    'max_upload' => env('MEDIA_MANAGER_MAX_FILE_SIZE', 51200), // KB (50MB)
    'allow_svg' => env('MEDIA_MANAGER_ALLOW_SVG', false),

    'allowed_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'video/mp4',
        'video/webm',
        'video/quicktime',
        'application/pdf',
        'application/zip',
        'text/plain',
    ],

    /*
    |--------------------------------------------------------------------------
    | Duplicate detection
    |--------------------------------------------------------------------------
    |
    | Files are fingerprinted (sha1) on upload. 'warn' uploads the file and
    | tells the UI it already existed, 'reuse' returns the existing media and
    | skips the upload, 'allow' ignores duplicates.
    |
    */
    'duplicates' => env('MEDIA_MANAGER_DUPLICATES', 'warn'),

    /*
    |--------------------------------------------------------------------------
    | UI Settings
    |--------------------------------------------------------------------------
    */
    'sidebar' => false,         // Default visibility of the folder tree
    'default_view' => 'grid',   // 'grid' or 'list'
    // 'auto' follows the host (html.dark / [data-bs-theme] / [data-theme]); or 'light' | 'dark'.
    // open({ theme }) overrides this per call.
    'default_theme' => env('MEDIA_MANAGER_DEFAULT_THEME', 'auto'),
    'allowed_locales' => ['en', 'tr'],

    /*
    |--------------------------------------------------------------------------
    | Media Collections & Pagination
    |--------------------------------------------------------------------------
    */
    'pagination' => env('MEDIA_MANAGER_PER_PAGE', 30),
    'per_page' => env('MEDIA_MANAGER_PER_PAGE', 30), // alias
    'collection' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Image Conversions (Spatie, always generated)
    |--------------------------------------------------------------------------
    |
    | Used for grid thumbnails. Theme specific sizes are NOT configured here:
    | the host app passes `variants` to MediaManager.open() (see README).
    |
    */
    'conversions' => [
        'thumb' => [300, 300],
        'medium' => [600, 600],
        'large' => [1200, 1200],
    ],

    // Also generate a full-size WebP copy of every image (extra work on each upload).
    'webp' => env('MEDIA_MANAGER_WEBP', true),

    /*
    |--------------------------------------------------------------------------
    | Image variants
    |--------------------------------------------------------------------------
    |
    | default_variants: used when open() does not pass `variants`. Each entry is
    |   ['name' => 'card', 'width' => 256, 'height' => 256]      exact size (cropped to fit)
    |   ['name' => 'product', 'ratio' => '3:4']                  largest crop of that ratio
    |   ['name' => 'product', 'ratio' => '3:4', 'width' => 900]  height derived from ratio
    |
    | Files are written as {original}-{WIDTHxHEIGHT}.{ext} next to the original,
    | which is never modified. `queue` runs generation on the queue instead of
    | during the upload request.
    |
    */
    'default_variants' => [],
    'max_variants' => 12,
    'max_dimension' => 8000,       // px; larger sources are not processed (GD memory)
    'image_quality' => 85,
    'queue' => env('MEDIA_MANAGER_QUEUE', false),
    'queue_connection' => env('MEDIA_MANAGER_QUEUE_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Usage resolver
    |--------------------------------------------------------------------------
    |
    | Class implementing Yazilim360\MediaManager\Contracts\MediaUsageResolver.
    | Deleting a media item that reports usages is refused (HTTP 409) unless the
    | request is forced. Keeps the package free of any app specific schema.
    |
    */
    'usage_resolver' => null,

    /*
    |--------------------------------------------------------------------------
    | Binaries
    |--------------------------------------------------------------------------
    | FFmpeg is used to generate video thumbnails and read video metadata.
    | Windows example:
    |   FFMPEG_PATH=C:\ffmpeg\bin\ffmpeg.exe
    |   FFPROBE_PATH=C:\ffmpeg\bin\ffprobe.exe
    */
    // Poster (thumbnail) frame: the second of the clip to grab (decimals allowed, e.g. 2.3). Kept inside short clips.
    'video_thumb_second' => env('MEDIA_MANAGER_VIDEO_THUMB_SECOND', 1),
    // Video thumbnails need no ffmpeg: when the server has none (or it fails) the uploader's browser grabs the frame
    // and sends it. Set to false to create them on the server only.
    'client_poster' => env('MEDIA_MANAGER_CLIENT_POSTER', true),
    'video_poster_width' => env('MEDIA_MANAGER_POSTER_WIDTH', 640),
    'poster_timeout' => env('MEDIA_MANAGER_POSTER_TIMEOUT', 60),
    'ffmpeg_path' => env('FFMPEG_PATH', '/usr/bin/ffmpeg'),
    'ffprobe_path' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),
    'ffmpeg_timeout' => env('FFMPEG_TIMEOUT', 900),
    'ffmpeg_threads' => env('FFMPEG_THREADS', 0),
];
