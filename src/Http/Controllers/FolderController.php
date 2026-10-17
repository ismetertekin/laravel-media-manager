<?php

namespace Yazilim360\MediaManager\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Yazilim360\MediaManager\Http\Requests\CreateFolderRequest;
use Yazilim360\MediaManager\Http\Requests\RenameFolderRequest;
use Yazilim360\MediaManager\Models\Folder;
use Yazilim360\MediaManager\Models\MediaManager;
use Yazilim360\MediaManager\Support\MediaStoragePathSynchronizer;
use Yazilim360\MediaManager\Support\MediaUsage;

class FolderController extends Controller
{
    /**
     * Folder tree. With parent_id only that folder's subtree is returned.
     *
     * GET /media-manager/api/folders?parent_id=
     */
    public function index(Request $request): JsonResponse
    {
        $all = Folder::query()->orderBy('name')->get();
        $byParent = $all->groupBy(fn (Folder $f) => $f->parent_id ?? 0);

        $rootId = $request->filled('parent_id') ? (int) $request->integer('parent_id') : 0;

        return response()->json(['data' => $this->buildTree($byParent, $rootId)]);
    }

    /**
     * POST /media-manager/api/folders  { name, parent_id? }
     */
    public function store(CreateFolderRequest $request): JsonResponse
    {
        $folder = Folder::create([
            'name' => $request->name,
            'parent_id' => $request->parent_id,
        ]);

        Storage::disk($this->disk())->makeDirectory($this->relative($folder));

        return response()->json([
            'message' => 'Folder created.',
            'data' => $this->folderData($folder),
        ], 201);
    }

    /**
     * Resolve (and create when missing) a nested folder path such as "themes/default/products".
     *
     * POST /media-manager/api/folders/ensure  { path }
     */
    public function ensure(Request $request): JsonResponse
    {
        $request->validate(['path' => ['required', 'string', 'max:500', 'regex:/^[^:*?"<>|]+$/u']]);

        $folder = Folder::ensurePath((string) $request->path);

        return response()->json(['data' => $folder ? $this->folderData($folder) : null]);
    }

    /**
     * POST /media-manager/api/folders/rename  { id, name }
     */
    public function rename(RenameFolderRequest $request, MediaStoragePathSynchronizer $pathSync): JsonResponse
    {
        $folder = Folder::findOrFail($request->id);
        $oldRelative = $this->relative($folder);

        DB::transaction(function () use ($folder, $request) {
            $folder->update(['name' => $request->name]);
        });
        $folder->refresh();

        $this->relocateTree($folder, $oldRelative, $pathSync);

        // folder_name is denormalised on the media rows of this exact folder.
        $this->mediaIn([$folder->id])->each(function (Media $item) use ($folder) {
            $item->setCustomProperty('folder_name', $folder->name);
            $item->save();
        });

        return response()->json([
            'message' => 'Folder renamed.',
            'data' => $this->folderData($folder),
        ]);
    }

    /**
     * Move a folder under another parent (null = root).
     *
     * POST /media-manager/api/folders/move  { id, parent_id }
     */
    public function move(Request $request, MediaStoragePathSynchronizer $pathSync): JsonResponse
    {
        $request->validate([
            'id' => ['required', 'integer', 'exists:media_folders,id'],
            'parent_id' => ['nullable', 'integer', 'exists:media_folders,id'],
        ]);

        $folder = Folder::findOrFail($request->id);
        $parent = $request->parent_id ? Folder::findOrFail($request->parent_id) : null;

        if ($parent && $parent->isSelfOrDescendantOf($folder)) {
            throw ValidationException::withMessages(['parent_id' => 'A folder cannot be moved into itself or one of its subfolders.']);
        }

        $sibling = Folder::query()
            ->where('parent_id', $parent?->id)
            ->where('name', $folder->name)
            ->where('id', '!=', $folder->id)
            ->exists();

        if ($sibling) {
            throw ValidationException::withMessages(['parent_id' => 'A folder with this name already exists in the destination.']);
        }

        $oldRelative = $this->relative($folder);

        $folder->parent_id = $parent?->id;
        $folder->slug = $this->uniqueSlugIn($folder, $parent?->id);
        $folder->save();
        $folder->refresh();

        $this->relocateTree($folder, $oldRelative, $pathSync);

        return response()->json([
            'message' => 'Folder moved.',
            'data' => $this->folderData($folder),
        ]);
    }

    /**
     * Delete a folder with its subfolders.
     *
     * keep_media=1 moves the files to the root, otherwise they are deleted
     * (refused with 409 if the usage resolver reports usages, unless force=1).
     *
     * DELETE /media-manager/api/folders/{id}?keep_media=1
     */
    public function destroy(int $id, Request $request, MediaStoragePathSynchronizer $pathSync): JsonResponse
    {
        $folder = Folder::findOrFail($id);
        $keepMedia = $request->boolean('keep_media');
        $media = $this->mediaIn($folder->descendantAndSelfIds());

        if (! $keepMedia && ! $request->boolean('force')) {
            $blocked = [];
            foreach ($media as $item) {
                if ($usages = MediaUsage::for($item)) {
                    $blocked[$item->id] = $usages;
                }
            }

            if ($blocked !== []) {
                return response()->json(['message' => 'Some media are in use.', 'usages' => $blocked], 409);
            }
        }

        $relative = $this->relative($folder);

        DB::transaction(function () use ($media, $keepMedia, $pathSync, $folder) {
            foreach ($media as $item) {
                if ($keepMedia) {
                    $oldDir = $pathSync->expectedDirectory($item);
                    $item->forgetCustomProperty('folder_id');
                    $item->forgetCustomProperty('folder_name');
                    $item->save();
                    $pathSync->relocate($item, $oldDir);
                } else {
                    $item->delete();
                }
            }

            $folder->delete(); // children cascade
        });

        $disk = Storage::disk($this->disk());
        if ($disk->exists($relative)) {
            $disk->deleteDirectory($relative);
        }

        return response()->json(['message' => 'Folder deleted.']);
    }

    // ─── helpers ───────────────────────────────────────────────

    /**
     * Moves the folder's directory on disk after its slug/parent changed.
     * Media files live below it, so their URLs keep resolving.
     */
    protected function relocateTree(Folder $folder, string $oldRelative, MediaStoragePathSynchronizer $pathSync): void
    {
        $newRelative = $this->relative($folder);
        $disk = Storage::disk($this->disk());

        if ($oldRelative === $newRelative) {
            return;
        }

        if ($disk->exists($oldRelative)) {
            $disk->makeDirectory(dirname($newRelative));
            $disk->move($oldRelative, $newRelative);
        } else {
            $disk->makeDirectory($newRelative);
        }

        // Safety net for rows that were not on disk where the old layout said.
        $this->mediaIn($folder->descendantAndSelfIds())
            ->each(fn (Media $item) => $pathSync->syncDiskPathForMedia($item));
    }

    protected function mediaIn(array $folderIds): Collection|\Illuminate\Support\Collection
    {
        return Media::query()
            ->where('model_type', MediaManager::class)
            ->whereIn('custom_properties->folder_id', $folderIds)
            ->get();
    }

    protected function uniqueSlugIn(Folder $folder, ?int $parentId): string
    {
        $base = Str::slug($folder->name) ?: 'folder';
        $slug = $base;
        $i = 1;

        while (Folder::query()->where('parent_id', $parentId)->where('slug', $slug)->where('id', '!=', $folder->id)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    protected function disk(): string
    {
        return config('media-manager.disk', 'public');
    }

    protected function relative(Folder $folder): string
    {
        return trim(config('media-manager.disk_path', 'media-manager'), '/').'/'.$folder->nestedSlugPath();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Folder>>  $byParent
     */
    private function buildTree($byParent, int $parentId): array
    {
        return ($byParent->get($parentId) ?? collect())
            ->map(fn (Folder $folder) => array_merge($this->folderData($folder), [
                'children' => $this->buildTree($byParent, $folder->id),
            ]))
            ->values()
            ->all();
    }

    private function folderData(Folder $folder): array
    {
        return [
            'id' => $folder->id,
            'name' => $folder->name,
            'slug' => $folder->slug,
            'parent_id' => $folder->parent_id,
        ];
    }
}
