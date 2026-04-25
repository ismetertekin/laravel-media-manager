<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    */
    'route_prefix' => env('MEDIA_MANAGER_ROUTE_PREFIX', 'media-manager'),
    'route_middleware' => ['web'],

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
    */
    'max_upload' => env('MEDIA_MANAGER_MAX_FILE_SIZE', 51200), // KB (50MB)

    'allowed_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'video/mp4',
        'video/quicktime',
        'application/pdf',
        'application/zip',
        'text/plain',
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Settings
    |--------------------------------------------------------------------------
    */
    'sidebar' => false,         // Default visibility of the folder tree
    'default_view' => 'grid',       // 'grid' or 'list'
    // open({ theme: 'light'|'dark' }) ile üst uygulama teması; verilmezse bu + localStorage
    'default_theme' => env('MEDIA_MANAGER_DEFAULT_THEME', 'light'),
    // translations API ve locale doğrulama (genişletmek için publish edip düzenleyin)
    'allowed_locales' => ['en', 'tr'],

    /*
    |--------------------------------------------------------------------------
    | Media Collections & Pagination
    |--------------------------------------------------------------------------
    */
    // API /files per_page (tek kaynak)
    'pagination' => env('MEDIA_MANAGER_PER_PAGE', 30),
    'per_page' => env('MEDIA_MANAGER_PER_PAGE', 30), // alias
    'collection' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Image Conversions
    |--------------------------------------------------------------------------
    */
    'conversions' => [
        'thumb' => [300, 300],
        'medium' => [600, 600],
        'large' => [1200, 1200],
    ],

    /*
    |--------------------------------------------------------------------------
    | Binaries
    |--------------------------------------------------------------------------
    | FFmpeg is used to generate video thumbnails.
    */
    'ffmpeg_path' => env('FFMPEG_PATH', '/usr/bin/ffmpeg'),
    'ffprobe_path' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),
];
