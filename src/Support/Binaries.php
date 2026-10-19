<?php

namespace Yazilim360\MediaManager\Support;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Locates ffmpeg / ffprobe. The configured path wins; when it is wrong (a very common case: a Linux
 * default on Windows, or ffprobe forgotten) the binary is searched next to ffmpeg and on the PATH.
 */
class Binaries
{
    /** @var array<string, string|null> */
    protected static array $cache = [];

    public static function ffmpeg(): ?string
    {
        return self::resolve('ffmpeg', config('media-manager.ffmpeg_path'));
    }

    public static function ffprobe(): ?string
    {
        $found = self::resolve('ffprobe', config('media-manager.ffprobe_path'));

        if ($found || ! ($ffmpeg = self::ffmpeg())) {
            return $found;
        }

        // ffprobe lives next to ffmpeg in every official build.
        foreach (['ffprobe', 'ffprobe.exe'] as $name) {
            $candidate = dirname($ffmpeg).DIRECTORY_SEPARATOR.$name;
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** First line of `binary -version`, or null when it cannot be executed. */
    public static function version(?string $binary): ?string
    {
        if (! $binary) {
            return null;
        }

        try {
            $process = new Process([$binary, '-version']);
            $process->setTimeout(10);
            $process->run();

            return $process->isSuccessful() ? trim(strtok($process->getOutput(), "\n") ?: '') : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    protected static function resolve(string $name, mixed $configured): ?string
    {
        $configured = is_string($configured) ? trim($configured, " \t\n\r\0\x0B\"'") : '';
        $key = $name.'|'.$configured;

        return self::$cache[$key] ??= (function () use ($name, $configured) {
            if ($configured !== '' && is_file($configured)) {
                return $configured;
            }

            return (new ExecutableFinder)->find($name);
        })();
    }
}
