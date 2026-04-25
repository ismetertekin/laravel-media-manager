<?php

namespace Yazilim360\MediaManager\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Yazilim360\MediaManager\Models\MediaManager;
use Yazilim360\MediaManager\Support\MediaStoragePathSynchronizer;

/**
 * DB’deki klasör (folder_id) ile disk üstü yol uyuşmazsa görüntü kırılır.
 * Aynı mantık UI’den klasör taşımada {@see MediaStoragePathSynchronizer} ile otomatik çalışır.
 */
class SyncMediaStoragePathsCommand extends Command
{
    protected $signature = 'media-manager:sync-storage-paths
                            {--dry-run : Sadece ne yapılacağını listele}';

    protected $description = 'Medya dosyalarını PathGenerator\'ın beklediği yola taşır (toplu onarım)';

    public function handle(MediaStoragePathSynchronizer $synchronizer): int
    {
        $diskName = config('media-manager.disk', 'public');
        $disk = Storage::disk($diskName);

        $count = 0;
        $fixed = 0;

        $query = Media::query()->where('model_type', MediaManager::class);

        foreach ($query->cursor() as $media) {
            $count++;
            $expectedDir = $synchronizer->expectedDirectory($media);
            $expectedFile = $expectedDir.'/'.$media->file_name;

            if ($disk->exists($expectedFile)) {
                continue;
            }

            $legacyDir = $synchronizer->findLegacyDirectory($media);

            if ($legacyDir === null) {
                $this->warn("  #{$media->id} «{$media->file_name}» — beklenen: {$expectedFile} | bilinen eski konumlarda yok.");

                continue;
            }

            if ($legacyDir === $expectedDir) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("  [dry-run] #{$media->id}: <fg=cyan>{$legacyDir}</> → <fg=green>{$expectedDir}</>");

                continue;
            }

            if ($synchronizer->syncDiskPathForMedia($media)) {
                $this->info("  #{$media->id}: taşındı → {$expectedDir}");
                $fixed++;
            }
        }

        $this->newLine();
        $this->info("Taranan medya: {$count}".($this->option('dry-run') ? '' : " | Taşınan: {$fixed}"));

        return self::SUCCESS;
    }
}
