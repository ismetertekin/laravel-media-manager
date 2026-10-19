<?php

namespace Yazilim360\MediaManager\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Yazilim360\MediaManager\Support\Binaries;
use Yazilim360\MediaManager\Support\UploadLimits;

/**
 * Checks everything the media manager depends on and explains what to fix.
 *
 *   php artisan media-manager:doctor
 */
class DoctorCommand extends Command
{
    protected $signature = 'media-manager:doctor';

    protected $description = 'Check PHP extensions, upload limits, storage, ffmpeg and routes protection';

    protected int $problems = 0;

    public function handle(): int
    {
        $this->components->info('Media Manager – environment check');

        $this->section('PHP');
        $this->check('GD extension', extension_loaded('gd'), 'Required for image variants. Enable extension=gd.');
        if (extension_loaded('gd')) {
            $gd = gd_info();
            $this->check('GD: JPEG / PNG / WebP', ! empty($gd['JPEG Support']) && ! empty($gd['PNG Support']), 'GD lacks JPEG or PNG support.');
            $this->check('GD: WebP', ! empty($gd['WebP Support']), 'GD has no WebP support: WebP uploads cannot be processed.', warn: true);
        }
        $this->check('fileinfo extension', extension_loaded('fileinfo'), 'Required to detect MIME types.');
        $this->check('exif extension', extension_loaded('exif'), 'Optional: phone photos may be cropped sideways without it.', warn: true);

        $this->section('Upload limits');
        $configured = (int) config('media-manager.max_upload');
        $effective = UploadLimits::effectiveKb();
        $this->line(sprintf(
            '  media-manager.max_upload = %s KB · upload_max_filesize = %s · post_max_size = %s · effective = %s KB',
            number_format($configured), ini_get('upload_max_filesize'), ini_get('post_max_size'), number_format($effective)
        ));
        $this->check('PHP allows the configured size', $effective >= $configured, 'PHP limits are lower than MEDIA_MANAGER_MAX_FILE_SIZE: raise upload_max_filesize and post_max_size in php.ini (and restart PHP).', warn: true);

        $this->section('Storage');
        $diskName = config('media-manager.disk', 'public');
        $this->checkDisk($diskName);
        if ($diskName === 'public') {
            $this->check('public/storage link', file_exists(public_path('storage')), 'Run `php artisan storage:link`, otherwise uploaded files return 404.');
        }

        $this->section('Database');
        foreach (['media', 'media_folders', 'media_manager_owners'] as $table) {
            $this->check("table {$table}", Schema::hasTable($table), 'Run `php artisan migrate`.');
        }

        $this->section('Video (ffmpeg)');
        $ffmpeg = Binaries::ffmpeg();
        $ffprobe = Binaries::ffprobe();
        $this->line('  configured FFMPEG_PATH  = '.(config('media-manager.ffmpeg_path') ?: '(none)'));
        $this->line('  configured FFPROBE_PATH = '.(config('media-manager.ffprobe_path') ?: '(none)'));
        $this->check('ffmpeg', (bool) $ffmpeg, 'Optional. Without it the uploader\'s browser creates the video thumbnail (client_poster). To create it on the server instead, install ffmpeg or set FFMPEG_PATH (quotes on Windows), then `php artisan config:clear`.', warn: true);
        $ffmpeg && $this->line('      '.($ffmpegVersion = Binaries::version($ffmpeg)).' ('.$ffmpeg.')');
        $this->check('ffprobe', (bool) $ffprobe, 'Optional: without it the browser reports the video duration and size. It is normally installed next to ffmpeg.', warn: true);
        if ($ffmpeg) {
            $this->check('ffmpeg runs', isset($ffmpegVersion) && $ffmpegVersion !== null, 'The file exists but could not be executed (permissions / wrong architecture).', warn: true);
        }

        $this->section('Access');
        $middleware = (array) config('media-manager.route_middleware', []);
        $this->line('  route_middleware = ['.implode(', ', $middleware).'] · authorize = '.(config('media-manager.authorize') ?: '(none)'));
        $protected = collect($middleware)->contains(fn ($m) => is_string($m) && ($m === 'auth' || str_starts_with($m, 'auth:') || str_starts_with($m, 'can:')))
            || config('media-manager.authorize');
        $this->check('routes require authentication', (bool) $protected, 'Anyone can list, upload and delete! Set MEDIA_MANAGER_MIDDLEWARE="web,auth:admin" (your guard).', warn: true);

        $this->section('Queue');
        $queued = (bool) config('media-manager.queue');
        $this->line('  variant generation on queue: '.($queued ? 'yes' : 'no (runs during the upload)'));
        if ($queued) {
            $this->check('queue worker driver', config('queue.default') !== 'sync' || app()->environment('testing'), 'QUEUE_CONNECTION=sync runs jobs inline; start a worker for real queueing.', warn: true);
        }

        $this->newLine();
        $this->problems === 0
            ? $this->components->info('Everything looks good.')
            : $this->components->error("{$this->problems} problem(s) need attention (see above).");

        return $this->problems === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function section(string $title): void
    {
        $this->newLine();
        $this->line("<options=bold>{$title}</>");
    }

    protected function check(string $label, bool $ok, string $hint, bool $warn = false): void
    {
        if ($ok) {
            $this->line("  <fg=green>✔</> {$label}");

            return;
        }

        $this->line('  '.($warn ? '<fg=yellow>!</>' : '<fg=red>✘</>')." {$label}");
        $this->line("      <fg=gray>{$hint}</>");

        if (! $warn) {
            $this->problems++;
        }
    }

    protected function checkDisk(string $diskName): void
    {
        $probe = trim(config('media-manager.disk_path', 'media-manager'), '/').'/.doctor-'.uniqid();

        try {
            $disk = Storage::disk($diskName);
            $disk->put($probe, 'ok');
            $ok = $disk->get($probe) === 'ok';
            $disk->delete($probe);
        } catch (\Throwable $e) {
            $ok = false;
            $this->line('      <fg=gray>'.$e->getMessage().'</>');
        }

        $this->check("disk '{$diskName}' is writable", $ok, 'Check the disk configuration and folder permissions.');
    }
}
