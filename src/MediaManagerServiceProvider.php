<?php

namespace Yazilim360\MediaManager;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Yazilim360\MediaManager\Console\SyncMediaStoragePathsCommand;
use Yazilim360\MediaManager\View\Components\MediaPickerComponent;

class MediaManagerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Spatie resolves path generators from config, not the container bind.
        $existing = config('media-library.custom_path_generators');
        $generators = array_merge(
            is_array($existing) ? $existing : [],
            [
                \Yazilim360\MediaManager\Models\MediaManager::class => \Yazilim360\MediaManager\Support\PathGenerator::class,
            ]
        );
        config(['media-library.custom_path_generators' => $generators]);

        // ─── Ensure Storage Directory Exists ──────────────────────
        $disk = config('media-manager.disk', 'public');
        $path = trim(config('media-manager.disk_path', 'media-manager'), '/');
        \Illuminate\Support\Facades\Storage::disk($disk)->makeDirectory($path);

        // ─── Routes ───────────────────────────────────────────────
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // ─── Views ────────────────────────────────────────────────
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'media-manager');

        // ─── Migrations ───────────────────────────────────────────
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // ─── Translations ─────────────────────────────────────────
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'media-manager');

        // ─── Blade Components ──────────────────────────────────────
        // Register <x-media-picker /> component
        Blade::component('media-picker', MediaPickerComponent::class);

        // ─── Publishable Resources ─────────────────────────────────
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncMediaStoragePathsCommand::class,
            ]);

            // Publish config
            $this->publishes([
                __DIR__ . '/../config/media-manager.php' => config_path('media-manager.php'),
            ], 'media-manager-config');

            // Publish views
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/media-manager'),
            ], 'media-manager-views');

            // Publish translations
            $this->publishes([
                __DIR__.'/../resources/lang' => resource_path('lang/vendor/media-manager'),
            ], 'media-manager-lang');

            // Publish migrations
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'media-manager-migrations');

            // Publish compiled assets (if using pre-built dist)
            $this->publishes([
                __DIR__ . '/../dist' => public_path('vendor/media-manager'),
            ], 'media-manager-assets');
        }
    }

    public function register(): void
    {
        // Merge package config with app config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/media-manager.php',
            'media-manager'
        );

    }
}
