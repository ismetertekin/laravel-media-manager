<?php

namespace Yazilim360\MediaManager\Support;

use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Grabs one frame of a video as its poster (the YouTube-style thumbnail shown in the library).
 *
 * Runs ffmpeg directly instead of through Spatie conversions, so a missing / misconfigured binary
 * simply means "no poster" and can never turn a successful upload into an error.
 *
 * Stored in custom_properties: poster (file name), poster_width, poster_height, poster_second, poster_status.
 */
class VideoPoster
{
    public function __construct(protected MediaAnalyzer $analyzer = new MediaAnalyzer) {}

    /** @return bool true when a poster was written */
    public function generate(Media $media, ?float $second = null): bool
    {
        $ffmpeg = Binaries::ffmpeg();

        if (! $ffmpeg || ! str_starts_with((string) $media->mime_type, 'video/')) {
            $this->mark($media, 'unavailable');

            return false;
        }

        $second = $this->secondFor($media, $second);

        try {
            $ok = (bool) $this->analyzer->withLocalCopy($media, function (string $source) use ($media, $ffmpeg, $second) {
                $tmp = tempnam(sys_get_temp_dir(), 'mmp_').'.jpg';

                try {
                    // A frame past the end of a stream yields no image: fall back to the first frame.
                    foreach (array_unique([$second, 0.0]) as $at) {
                        if ($this->extract($ffmpeg, $source, $at, $tmp) && filesize($tmp) > 0) {
                            return $this->storeFile($media, $tmp, $at, 'ffmpeg');
                        }
                    }

                    return false;
                } finally {
                    @unlink($tmp);
                }
            });

            $this->mark($media, $ok ? 'ready' : 'failed');

            return $ok;
        } catch (Throwable $e) {
            report($e);
            $this->mark($media, 'failed');

            return false;
        }
    }

    /** Public URL of the poster, or null. */
    public function url(Media $media): ?string
    {
        $file = $media->getCustomProperty('poster');

        return $file
            ? rtrim(dirname($media->getUrl()), '/').'/conversions/'.rawurlencode($file).'?v='.(int) $media->getCustomProperty('poster_at', 0)
            : null;
    }

    public function delete(Media $media): void
    {
        if ($file = $media->getCustomProperty('poster')) {
            Storage::disk($media->disk)->delete($this->directory($media).'/'.$file);
        }

        foreach (['poster', 'poster_width', 'poster_height', 'poster_second', 'poster_status', 'poster_source', 'poster_at'] as $key) {
            $media->forgetCustomProperty($key);
        }
    }

    /** The frame to use: the configured second, kept inside the clip. */
    public function secondFor(Media $media, ?float $second = null): float
    {
        $second ??= (float) config('media-manager.video_thumb_second', 1);
        $duration = (float) $media->getCustomProperty('duration', 0);

        if ($duration > 0) {
            $second = min($second, max(0.0, $duration - 0.5));
        }

        return max(0.0, round($second, 2));
    }

    protected function extract(string $ffmpeg, string $source, float $at, string $target): bool
    {
        @unlink($target);

        $width = max(16, (int) config('media-manager.video_poster_width', 640));

        $process = new Process([
            $ffmpeg, '-hide_banner', '-loglevel', 'error', '-y',
            '-ss', (string) $at, '-i', $source,
            '-frames:v', '1',
            '-vf', "scale='min({$width},iw)':-2",
            '-q:v', '3',
            $target,
        ]);
        $process->setTimeout((float) config('media-manager.poster_timeout', 60));
        $process->run();

        return $process->isSuccessful() && is_file($target);
    }

    /**
     * Stores an image file as the poster (the file is any readable JPEG/PNG/GIF/WebP; it is re-encoded to JPEG
     * so nothing but pixels is ever served). Used for ffmpeg frames and for frames captured in the browser.
     */
    public function storeFile(Media $media, string $path, ?float $second = null, string $source = 'browser'): bool
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mmq_');

        try {
            $editor = new ImageEditor;
            $img = $editor->load($path);
            $editor->save($img, $tmp, 'image/jpeg');

            return $this->store($media, $tmp, $second, $source);
        } finally {
            @unlink($tmp);
        }
    }

    protected function store(Media $media, string $tmp, ?float $at, string $source = 'ffmpeg'): bool
    {
        $name = pathinfo($media->file_name, PATHINFO_FILENAME).'-poster.jpg';
        $stream = fopen($tmp, 'r');

        if ($stream === false) {
            return false;
        }

        $this->delete($media);
        Storage::disk($media->disk)->put($this->directory($media).'/'.$name, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        [$w, $h] = @getimagesize($tmp) ?: [null, null];
        $media->setCustomProperty('poster', $name);
        $media->setCustomProperty('poster_at', time()); // cache-buster: a replaced poster keeps its file name
        $media->setCustomProperty('poster_source', $source);
        $at !== null && $media->setCustomProperty('poster_second', $at);
        $w && $media->setCustomProperty('poster_width', $w);
        $h && $media->setCustomProperty('poster_height', $h);
        $media->setCustomProperty('poster_status', 'ready');
        $media->save();

        return true;
    }

    protected function directory(Media $media): string
    {
        return rtrim(dirname($media->getPathRelativeToRoot()), '/').'/conversions';
    }

    protected function mark(Media $media, string $status): void
    {
        $media->setCustomProperty('poster_status', $status);
        $media->save();
    }
}
