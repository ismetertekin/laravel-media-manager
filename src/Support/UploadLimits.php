<?php

namespace Yazilim360\MediaManager\Support;

/**
 * The upload size the server will really accept: the smaller of the package setting and PHP's own limits.
 * Shown to the browser so it can reject a file with a clear message instead of a bare HTTP error.
 */
class UploadLimits
{
    /** @return int kilobytes */
    public static function effectiveKb(): int
    {
        $limits = [(int) config('media-manager.max_upload', 51200)];

        if (($upload = self::bytes((string) ini_get('upload_max_filesize'))) > 0) {
            $limits[] = intdiv($upload, 1024);
        }

        // post_max_size also has to hold the other form fields.
        if (($post = self::bytes((string) ini_get('post_max_size'))) > 0) {
            $limits[] = max(1, intdiv($post, 1024) - 64);
        }

        return max(1, min(array_filter($limits, fn ($v) => $v > 0)));
    }

    public static function bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return 0; // unlimited
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
