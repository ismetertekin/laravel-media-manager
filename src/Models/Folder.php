<?php

namespace Yazilim360\MediaManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        $slug = $base;
        $counter = 1;

        while (
            static::where('slug', $slug)
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

        while ($folder) {
            array_unshift($segments, $folder->slug);
            $folder = $folder->parent_id
                ? static::query()->whereKey($folder->parent_id)->first()
                : null;
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

        while ($folder) {
            array_unshift($crumbs, ['id' => $folder->id, 'name' => $folder->name, 'slug' => $folder->slug]);
            $folder = $folder->parent;
        }

        return $crumbs;
    }
}
