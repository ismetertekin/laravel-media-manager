<?php

use Illuminate\Support\Facades\Route;
use Yazilim360\MediaManager\Http\Controllers\FolderController;
use Yazilim360\MediaManager\Http\Controllers\MediaController;
use Yazilim360\MediaManager\Http\Controllers\PageController;
use Yazilim360\MediaManager\Http\Controllers\TranslationsController;
use Yazilim360\MediaManager\Http\Middleware\AuthorizeMediaManager;

$prefix = config('media-manager.route_prefix', 'media-manager');
$middleware = array_merge((array) config('media-manager.route_middleware', ['web', 'auth']), [AuthorizeMediaManager::class]);

Route::middleware($middleware)
    ->prefix($prefix)
    ->group(function () {
        // Standalone demo page
        Route::get('/', [PageController::class, 'index'])->name('media-manager.index');

        // API routes (JSON)
        Route::prefix('api')->group(function () {

            Route::get('/translations', TranslationsController::class)->name('media-manager.api.translations');

            // Media files
            Route::get('/files', [MediaController::class, 'index'])->name('media-manager.api.files.index');
            Route::post('/upload', [MediaController::class, 'upload'])->name('media-manager.api.files.upload');
            Route::get('/files/{id}', [MediaController::class, 'show'])->whereNumber('id')->name('media-manager.api.files.show');
            Route::patch('/files/{id}', [MediaController::class, 'update'])->whereNumber('id')->name('media-manager.api.files.update');
            Route::delete('/files/{id}', [MediaController::class, 'destroy'])->whereNumber('id')->name('media-manager.api.files.destroy');
            Route::post('/files/{id}/variants', [MediaController::class, 'variants'])->whereNumber('id')->name('media-manager.api.files.variants');

            // File operations
            Route::post('/files/delete', [MediaController::class, 'bulkDestroy'])->name('media-manager.api.files.bulk-destroy');
            Route::post('/files/move', [MediaController::class, 'move'])->name('api.files.move');
            Route::post('/files/copy', [MediaController::class, 'copy'])->name('api.files.copy');

            // Folders
            Route::get('/folders', [FolderController::class, 'index'])->name('api.folders.index');
            Route::post('/folders', [FolderController::class, 'store'])->name('api.folders.store');
            Route::post('/folders/ensure', [FolderController::class, 'ensure'])->name('api.folders.ensure');
            Route::post('/folders/rename', [FolderController::class, 'rename'])->name('api.folders.rename');
            Route::post('/folders/move', [FolderController::class, 'move'])->name('api.folders.move');
            Route::delete('/folders/{id}', [FolderController::class, 'destroy'])->whereNumber('id')->name('api.folders.destroy');
        });
    });
