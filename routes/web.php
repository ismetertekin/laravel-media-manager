<?php

use Illuminate\Support\Facades\Route;
use Yazilim360\MediaManager\Http\Controllers\FolderController;
use Yazilim360\MediaManager\Http\Controllers\MediaController;
use Yazilim360\MediaManager\Http\Controllers\PageController;
use Yazilim360\MediaManager\Http\Controllers\TranslationsController;

$prefix = config('media-manager.route_prefix', 'media-manager');
$middleware = config('media-manager.route_middleware', ['web']);

// Standalone demo page (web)
Route::middleware($middleware)
    ->prefix($prefix)
    ->group(function () {
        Route::get('/', [PageController::class, 'index'])->name('media-manager.index');

        // API routes (JSON)
        Route::prefix('api')->group(function () {

            Route::get('/translations', TranslationsController::class)->name('media-manager.api.translations');

            // Media files
            Route::get('/files', [MediaController::class, 'index'])->name('media-manager.api.files.index');
            Route::post('/upload', [MediaController::class, 'upload'])->name('media-manager.api.files.upload');
            Route::delete('/files/{id}', [MediaController::class, 'destroy'])->name('media-manager.api.files.destroy');

            // Folders
            Route::get('/folders', [FolderController::class, 'index'])->name('api.folders.index');
            Route::post('/folders', [FolderController::class, 'store'])->name('api.folders.store');
            Route::post('/folders/rename', [FolderController::class, 'rename'])->name('api.folders.rename');
            Route::delete('/folders/{id}', [FolderController::class, 'destroy'])->name('api.folders.destroy');

            // File Operations (New)
            Route::post('/files/move', [MediaController::class, 'move'])->name('api.files.move');
            Route::post('/files/copy', [MediaController::class, 'copy'])->name('api.files.copy');
        });
    });
