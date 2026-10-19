<?php

namespace Yazilim360\MediaManager\Console;

use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Yazilim360\MediaManager\Models\MediaManager;
use Yazilim360\MediaManager\Support\Binaries;
use Yazilim360\MediaManager\Support\MediaAnalyzer;
use Yazilim360\MediaManager\Support\VideoPoster;

/**
 * Creates the poster (thumbnail frame) for videos that have none, e.g. everything uploaded before ffmpeg was set up.
 *
 *   php artisan media-manager:video-posters             # only videos without a poster
 *   php artisan media-manager:video-posters --force --second=2.3
 */
class VideoPostersCommand extends Command
{
    protected $signature = 'media-manager:video-posters
                            {--force : Recreate posters that already exist}
                            {--second= : Frame to grab, in seconds (default: media-manager.video_thumb_second)}
                            {--ids=* : Only these media ids}';

    protected $description = 'Generate video poster frames with ffmpeg';

    public function handle(VideoPoster $posters, MediaAnalyzer $analyzer): int
    {
        if (! Binaries::ffmpeg()) {
            $this->error('ffmpeg was not found. Run `php artisan media-manager:doctor` and set FFMPEG_PATH.');

            return self::FAILURE;
        }

        $query = Media::query()
            ->where('model_type', MediaManager::class)
            ->where('mime_type', 'like', 'video/%');

        if ($ids = array_filter((array) $this->option('ids'))) {
            $query->whereIn('id', $ids);
        }

        $second = $this->option('second') !== null ? (float) $this->option('second') : null;
        $done = $skipped = $failed = 0;

        foreach ($query->cursor() as $media) {
            if (! $this->option('force') && $media->getCustomProperty('poster')) {
                $skipped++;

                continue;
            }

            if ($media->getCustomProperty('duration') === null) {
                $analyzer->analyze($media); // also fills width/height/duration for older uploads
                $media->refresh();
            }

            if ($posters->generate($media, $second)) {
                $done++;
            } else {
                $failed++;
                $this->warn("  #{$media->id} {$media->file_name}: no frame could be extracted");
            }
        }

        $this->info("Posters created: {$done} | skipped: {$skipped}".($failed ? " | failed: {$failed}" : ''));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
