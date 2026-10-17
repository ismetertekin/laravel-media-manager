<?php

namespace Yazilim360\MediaManager\Support;

use Illuminate\Validation\ValidationException;

/**
 * A normalised image variant request coming from MediaManager.open({ variants }).
 *
 * - width + height  → exact output size, source is cropped to that ratio
 * - ratio (+ width) → crop of that ratio; without width the crop is kept at full resolution
 */
final class VariantSpec
{
    public function __construct(
        public readonly string $name,
        public readonly string $ratioKey,
        public readonly int $ratioW,
        public readonly int $ratioH,
        public readonly ?int $width,
        public readonly ?int $height,
    ) {}

    /**
     * @param  array<int, mixed>|string|null  $input  decoded array or JSON string
     * @return list<VariantSpec>
     */
    public static function parseMany(array|string|null $input): array
    {
        if (is_string($input)) {
            $input = $input === '' ? [] : json_decode($input, true);
            if (! is_array($input)) {
                throw ValidationException::withMessages(['variants' => 'Invalid variants payload.']);
            }
        }

        $input = array_values($input ?? []);
        $max = (int) config('media-manager.max_variants', 12);

        if (count($input) > $max) {
            throw ValidationException::withMessages(['variants' => "At most {$max} variants are allowed."]);
        }

        $specs = [];
        $names = [];

        foreach ($input as $i => $row) {
            $spec = self::parse(is_array($row) ? $row : [], $i);

            if (isset($names[$spec->name])) {
                throw ValidationException::withMessages(["variants.{$i}.name" => 'Duplicate variant name.']);
            }

            $names[$spec->name] = true;
            $specs[] = $spec;
        }

        return $specs;
    }

    public static function parse(array $row, int $index = 0): self
    {
        $key = "variants.{$index}";
        $limit = (int) config('media-manager.max_dimension', 8000);
        $name = (string) ($row['name'] ?? '');

        if (! preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/i', $name)) {
            throw ValidationException::withMessages(["{$key}.name" => 'Variant name must be 1-32 chars (letters, digits, - or _).']);
        }

        $width = isset($row['width']) ? (int) $row['width'] : null;
        $height = isset($row['height']) ? (int) $row['height'] : null;

        foreach (['width' => $width, 'height' => $height] as $field => $value) {
            if ($value !== null && ($value < 1 || $value > $limit)) {
                throw ValidationException::withMessages(["{$key}.{$field}" => "Must be between 1 and {$limit}."]);
            }
        }

        if ($width !== null && $height !== null) {
            [$rw, $rh] = self::reduce($width, $height);
        } elseif (isset($row['ratio']) && preg_match('/^(\d{1,4})\s*[:x\/]\s*(\d{1,4})$/', (string) $row['ratio'], $m) && (int) $m[1] > 0 && (int) $m[2] > 0) {
            [$rw, $rh] = self::reduce((int) $m[1], (int) $m[2]);

            if ($width !== null) {
                $height = max(1, (int) round($width * $rh / $rw));
            } elseif ($height !== null) {
                $width = max(1, (int) round($height * $rw / $rh));
            }
        } else {
            throw ValidationException::withMessages(["{$key}" => 'Provide width and height, or a ratio like "3:4".']);
        }

        return new self($name, "{$rw}:{$rh}", $rw, $rh, $width, $height);
    }

    public static function reduce(int $a, int $b): array
    {
        $g = self::gcd($a, $b);

        return [intdiv($a, $g), intdiv($b, $g)];
    }

    private static function gcd(int $a, int $b): int
    {
        return $b === 0 ? max($a, 1) : self::gcd($b, $a % $b);
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'ratio' => $this->ratioKey,
            'width' => $this->width,
            'height' => $this->height,
        ], fn ($v) => $v !== null);
    }

    /** Distinct ratio keys of a spec list (the crop steps the UI has to show). */
    public static function ratios(array $specs): array
    {
        return array_values(array_unique(array_map(fn (self $s) => $s->ratioKey, $specs)));
    }
}
