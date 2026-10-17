<?php

namespace Yazilim360\MediaManager\Console;

use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Yazilim360\MediaManager\Models\Folder;
use Yazilim360\MediaManager\Models\MediaManager;
use Yazilim360\MediaManager\Support\ImageEditor;
use Yazilim360\MediaManager\Support\VariantGenerator;
use Yazilim360\MediaManager\Support\VariantSpec;

/**
 * Rebuilds theme variants for existing images, e.g. after the host switched theme.
 *
 *   php artisan media-manager:regenerate-variants --specs='[{"name":"card","width":256,"height":256}]'
 *   php artisan media-manager:regenerate-variants --folder=themes/default --stored
 */
class RegenerateVariantsCommand extends Command
{
    protected $signature = 'media-manager:regenerate-variants
                            {--specs= : JSON array of variant specs (default: media-manager.default_variants)}
                            {--stored : Use the specs stored on each media item instead}
                            {--folder= : Only media in this folder path (includes subfolders)}
                            {--ids=* : Only these media ids}';

    protected $description = 'Regenerate theme image variants from the original files';

    public function handle(VariantGenerator $generator): int
    {
        $specs = [];

        if (! $this->option('stored')) {
            $json = $this->option('specs');
            $specs = VariantSpec::parseMany($json ?: config('media-manager.default_variants', []));

            if ($specs === []) {
                $this->error('No variants given. Pass --specs=JSON, set media-manager.default_variants or use --stored.');

                return self::FAILURE;
            }
        }

        $query = Media::query()
            ->where('model_type', MediaManager::class)
            ->whereIn('mime_type', ImageEditor::MIMES);

        if ($path = $this->option('folder')) {
            $folder = Folder::findByPath($path);
            if (! $folder) {
                $this->error("Folder not found: {$path}");

                return self::FAILURE;
            }
            $query->whereIn('custom_properties->folder_id', $folder->descendantAndSelfIds());
        }

        if ($ids = array_filter((array) $this->option('ids'))) {
            $query->whereIn('id', $ids);
        }

        $done = 0;
        $failed = 0;

        foreach ($query->cursor() as $media) {
            $use = $specs ?: VariantSpec::parseMany((array) $media->getCustomProperty('variant_specs', []));
            if ($use === []) {
                continue;
            }

            try {
                $generator->generate($media, $use);
                $done++;
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("  #{$media->id}: {$e->getMessage()}");
            }
        }

        $this->info("Regenerated: {$done}".($failed ? " | Failed: {$failed}" : ''));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
