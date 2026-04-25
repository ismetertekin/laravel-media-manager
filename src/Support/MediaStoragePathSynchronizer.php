<?php

namespace Yazilim360\MediaManager\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Yazilim360\MediaManager\Models\Folder;
use Yazilim360\MediaManager\Models\MediaManager;

/**
 * PathGenerator ile disk üstü klasörün eşleşmesini sağlar.
 * UI’den klasör taşıma sadece custom_properties güncelliyorsa dosyalar eski yolda kalır; bu sınıf taşır.
 */
class MediaStoragePathSynchronizer
{
    public function __construct(
        protected PathGenerator $pathGenerator = new PathGenerator,
    ) {}

    /**
     * Medya kaydı MediaManager’a ait değilse veya zaten doğru yerdeyse false.
     */
    public function syncDiskPathForMedia(Media $media): bool
    {
        if (! $this->isMediaManagerMedia($media)) {
            return false;
        }

        $disk = $this->disk($media);
        $base = trim(config('media-manager.disk_path', 'media-manager'), '/');
        $expectedDir = rtrim($this->pathGenerator->getPath($media), '/');
        $expectedFile = $expectedDir.'/'.$media->file_name;

        if ($disk->exists($expectedFile)) {
            return false;
        }

        $legacyDir = $this->resolveLegacyDir($disk, $base, $media, $expectedDir);

        if ($legacyDir === null || $legacyDir === $expectedDir) {
            return false;
        }

        $this->moveTree($disk, $legacyDir, $expectedDir);

        if ($disk->exists($legacyDir)) {
            $disk->deleteDirectory($legacyDir);
        }

        return true;
    }

    /**
     * Dry-run / rapor için: dosya beklenen yerde değilse eski kök dizin (taşıma kaynağı).
     */
    public function findLegacyDirectory(Media $media): ?string
    {
        if (! $this->isMediaManagerMedia($media)) {
            return null;
        }

        $disk = $this->disk($media);
        $base = trim(config('media-manager.disk_path', 'media-manager'), '/');
        $expectedDir = rtrim($this->pathGenerator->getPath($media), '/');
        $expectedFile = $expectedDir.'/'.$media->file_name;

        if ($disk->exists($expectedFile)) {
            return null;
        }

        return $this->resolveLegacyDir($disk, $base, $media, $expectedDir);
    }

    public function expectedDirectory(Media $media): string
    {
        return rtrim($this->pathGenerator->getPath($media), '/');
    }

    protected function isMediaManagerMedia(Media $media): bool
    {
        return $media->model_type === MediaManager::class;
    }

    protected function disk(Media $media): Filesystem
    {
        return Storage::disk($media->disk ?: config('media-manager.disk', 'public'));
    }

    protected function moveTree(Filesystem $disk, string $legacyDir, string $expectedDir): void
    {
        $disk->makeDirectory($expectedDir);

        foreach ($disk->allFiles($legacyDir) as $path) {
            $suffix = Str::after($path, $legacyDir.'/');
            $target = $expectedDir.'/'.$suffix;
            $disk->makeDirectory(dirname($target));
            $disk->move($path, $target);
        }
    }

    protected function resolveLegacyDir(Filesystem $disk, string $base, Media $media, string $expectedDir): ?string
    {
        $candidates = [];

        $candidates[] = $base.'/'.$media->id;
        $candidates[] = $base.'/products/'.$media->id;

        $folderId = $media->getCustomProperty('folder_id');
        if ($folderId && ($folder = Folder::find($folderId))) {
            $slugPath = trim($folder->nestedSlugPath(), '/');
            if ($slugPath !== '') {
                $candidates[] = $base.'/'.$slugPath.'/'.$media->id;
            }
        }

        $candidates = array_unique(array_filter($candidates));

        foreach ($candidates as $dir) {
            if ($dir === $expectedDir) {
                continue;
            }
            if ($disk->exists($dir.'/'.$media->file_name)) {
                return $dir;
            }
        }

        return null;
    }
}
