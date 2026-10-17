<?php

namespace Yazilim360\MediaManager\Support;

use FFMpeg\FFProbe;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Reads technical metadata (pixel size, video duration) into custom_properties.
 */
class MediaAnalyzer
{
    public function __construct(protected ImageEditor $editor = new ImageEditor) {}

    public function analyze(Media $media): void
    {
        $mime = (string) $media->mime_type;

        try {
            if (ImageEditor::supports($mime) || $mime === 'image/svg+xml') {
                $this->analyzeImage($media, $mime);
            } elseif (str_starts_with($mime, 'video/')) {
                $this->analyzeVideo($media);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function analyzeImage(Media $media, string $mime): void
    {
        $this->withLocalCopy($media, function (string $path) use ($media, $mime) {
            $dims = $mime === 'image/svg+xml' ? null : $this->editor->dimensions($path, $mime);

            if ($dims) {
                $media->setCustomProperty('width', $dims[0]);
                $media->setCustomProperty('height', $dims[1]);
                $media->save();
            }
        });
    }

    protected function analyzeVideo(Media $media): void
    {
        $this->withLocalCopy($media, function (string $path) use ($media) {
            foreach ($this->videoInfo($path) as $key => $value) {
                $media->setCustomProperty($key, $value);
            }

            $media->save();
        });
    }

    /**
     * width / height / duration of a local video file (empty when ffprobe is unavailable).
     * Safe to call before the file is attached to a media model.
     *
     * @return array{width?:int,height?:int,duration?:float}
     */
    public function videoInfo(string $path): array
    {
        $ffprobe = config('media-manager.ffprobe_path');
        if (! $ffprobe || ! is_file($ffprobe)) {
            return [];
        }

        try {
            $probe = FFProbe::create(['ffprobe.binaries' => $ffprobe]);
            $info = [];

            if ($stream = $probe->streams($path)->videos()->first()) {
                $info['width'] = (int) $stream->get('width');
                $info['height'] = (int) $stream->get('height');
            }

            if ($duration = $probe->format($path)->get('duration')) {
                $info['duration'] = round((float) $duration, 2);
            }

            return $info;
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Run $callback with a readable local path to the original file (downloads
     * from remote disks into a temp file).
     */
    public function withLocalCopy(Media $media, callable $callback): mixed
    {
        $disk = Storage::disk($media->disk);
        $relative = $media->getPathRelativeToRoot();

        if ($media->getDiskDriverName() === 'local') {
            return $callback($disk->path($relative));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'mm_');
        try {
            file_put_contents($tmp, $disk->get($relative));

            return $callback($tmp);
        } finally {
            @unlink($tmp);
        }
    }
}
