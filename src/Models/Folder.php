<?php

namespace Yazilim360\MediaManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Represents a virtual folder in the media manager.
 * Folders are stored in the `media_folders` table and support
 * hierarchical parent/child relationships.
 */
class Folder extends Model
{
    protected $table = 'media_folders';

    protected $fillable = ['name', 'slug', 'parent_id'];

    /**
     * Automatically generate a slug when the name is set.
     */
    protected static function booted(): void
    {
        static::creating(function (Folder $folder) {
            if (empty($folder->slug)) {
                $folder->slug = static::generateUniqueSlug($folder->name, $folder->parent_id);
            }
        });

        static::updating(function (Folder $folder) {
            if ($folder->isDirty('name')) {
                $folder->slug = static::generateUniqueSlug($folder->name, $folder->parent_id, $folder->id);
            }
        });
    }

    protected static function generateUniqueSlug(string $name, ?int $parentId = null, ?int $excludeId = null): string
    {
        $base = Str::slug($name);
        $slug = $base !== '' ? $base : 'folder';
        $counter = 1;

        while (
            static::query()
                ->where('slug', $slug)
                ->where('parent_id', $parentId)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * Ensure a nested folder path exists (e.g. "themes/default/product-img/electronics").
     * Creates missing segments and matching disk directories.
     */
    public static function ensurePath(string $path): ?self
    {
        $segments = array_values(array_filter(
            array_map(static fn (string $segment): string => trim($segment), explode('/', str_replace('\\', '/', $path))),
            static fn (string $segment): bool => $segment !== '' && $segment !== '.',
        ));

        if ($segments === []) {
            return null;
        }

        $parentId = null;
        $folder = null;

        foreach ($segments as $segment) {
            $folder = static::query()->firstOrCreate(
                [
                    'name' => $segment,
                    'parent_id' => $parentId,
                ],
            );
            $parentId = $folder->id;
        }

        if ($folder instanceof self) {
            $disk = config('media-manager.disk', 'public');
            $base = trim(config('media-manager.disk_path', 'media-manager'), '/');
            Storage::disk($disk)->makeDirectory($base.'/'.$folder->nestedSlugPath());
        }

        return $folder;
    }

    /**
     * Resolve an existing folder by nested path ("themes/default/brands").
     */
    public static function findByPath(string $path): ?self
    {
        $segments = array_values(array_filter(
            array_map(static fn (string $segment): string => trim($segment), explode('/', str_replace('\\', '/', $path))),
            static fn (string $segment): bool => $segment !== '' && $segment !== '.',
        ));

        if ($segments === []) {
            return null;
        }

        $parentId = null;
        $folder = null;

        foreach ($segments as $segment) {
            $folder = static::query()
                ->where('name', $segment)
                ->where('parent_id', $parentId)
                ->first();

            if (! $folder instanceof self) {
                return null;
            }

            $parentId = $folder->id;
        }

        return $folder;
    }

    /**
     * @return list<int>
     */
    public function descendantAndSelfIds(): array
    {
        $ids = [(int) $this->id];
        $frontier = [(int) $this->id];

        while ($frontier !== []) {
            $children = static::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $frontier = [];

            foreach ($children as $childId) {
                if (! in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $frontier[] = $childId;
                }
            }
        }

        return $ids;
    }

    /**
     * True when $this is $other or lives anywhere below it (used to forbid cycles on move).
     */
    public function isSelfOrDescendantOf(self $other): bool
    {
        return in_array((int) $this->id, $other->descendantAndSelfIds(), true);
    }

    /**
     * Parent folder relationship.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    /**
     * Child folders relationship.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    /**
     * Slug path from root (e.g. "parent-slug/child-slug") for disk layout under disk_path.
     */
    public function nestedSlugPath(): string
    {
        $segments = [];
        $folder = $this;
        $guard = 0;

        while ($folder && $guard < 64) {
            array_unshift($segments, $folder->slug);
            $folder = $folder->parent_id
                ? static::query()->whereKey($folder->parent_id)->first()
                : null;
            $guard++;
        }

        return implode('/', $segments);
    }

    /**
     * Build a breadcrumb path for this folder (array of ancestors + self).
     */
    public function breadcrumbs(): array
    {
        $crumbs = [];
        $folder = $this;
        $guard = 0;

        while ($folder && $guard < 64) {
            array_unshift($crumbs, ['id' => $folder->id, 'name' => $folder->name, 'slug' => $folder->slug]);
            $folder = $folder->parent;
            $guard++;
        }

        return $crumbs;
    }
}
