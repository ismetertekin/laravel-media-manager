<?php

namespace Yazilim360\MediaManager\Support;

/**
 * Conservative check for SVG uploads. SVG is XML that can embed scripts and is
 * served from the app's own origin, so anything active is rejected.
 */
class SvgGuard
{
    public static function isSafe(string $path): bool
    {
        $svg = @file_get_contents($path, false, null, 0, 2 * 1024 * 1024);
        if ($svg === false || ! preg_match('/<svg[\s>]/i', $svg)) {
            return false;
        }

        $forbidden = [
            '/<\s*script/i',
            '/<\s*foreignObject/i',
            '/<\s*iframe/i',
            '/<\s*embed/i',
            '/<\s*object/i',
            '/\son[a-z]+\s*=/i',
            '/javascript\s*:/i',
            '/data\s*:\s*text\/html/i',
            '/<!ENTITY/i',
            '/<\s*use[^>]+(xlink:)?href\s*=\s*["\']\s*(https?:|\/\/)/i',
        ];

        foreach ($forbidden as $pattern) {
            if (preg_match($pattern, $svg)) {
                return false;
            }
        }

        return true;
    }
}
