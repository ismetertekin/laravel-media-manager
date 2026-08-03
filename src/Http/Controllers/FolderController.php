<?php

namespace Yazilim360\MediaManager\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Yazilim360\MediaManager\Http\Requests\CreateFolderRequest;
use Yazilim360\MediaManager\Http\Requests\RenameFolderRequest;
use Yazilim360\MediaManager\Models\Folder;
use Yazilim360\MediaManager\Models\MediaManager;
use Yazilim360\MediaManager\Support\MediaStoragePathSynchronizer;

class FolderController extends Controller
{
    /**
     * List all folders as a nested tree (roots), or direct children of parent_id.
     *
     * GET /media-manager/api/folders?parent_id=
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->filled('parent_id')) {
            $parentId = (int) $request->integer('parent_id');
            $folders = Folder::query()
                ->with('children')
                ->where('parent_id', $parentId)
                ->orderBy('name')
                ->get();

            return response()->json(['data' => $this->buildTree($folders)]);
        }

        $folders = Folder::with('children')->whereNull('parent_id')->orderBy('name')->get();

        return response()->json(['data' => $this->buildTree($folders)]);
    }

    /**
     * Create a new virtual folder.
     *
     * POST /media-manager/api/folders
     * Body: name, parent_id (optional)
     */
    public function store(CreateFolderRequest $request): JsonResponse
    {
        $folder = Folder::create([
            'name' => $request->name,
            'parent_id' => $request->parent_id,
        ]);

        $disk = config('media-manager.disk', 'public');
        $base = trim(config('media-manager.disk_path', 'media-manager'), '/');
        $relative = $base.'/'.$folder->nestedSlugPath();
        Storage::disk($disk)->makeDirectory($relative);

        return response()->json([
            'message' => 'Folder created.',
            'data' => $this->folderData($folder),
        ], 201);
    }

    /**
     * Rename an existing folder.
     *
     * POST /media-manager/api/folders/rename
     * Body: id, name
     */
    public function rename(RenameFolderRequest $request, MediaStoragePathSynchronizer $pathSync): JsonResponse
    {
        $folder = Folder::findOrFail($request->id);
        $disk = config('media-manager.disk', 'public');
        $base = trim(config('media-manager.disk_path', 'media-manager'), '/');
        $oldRelative = $base.'/'.$folder->nestedSlugPath();

        $folder->update(['name' => $request->name]);
        $folder->refresh();

        $newRelative = $base.'/'.$folder->nestedSlugPath();

        if ($oldRelative !== $newRelative) {
            if (Storage::disk($disk)->exists($oldRelative)) {
                Storage::disk($disk)->makeDirectory(dirname($newRelative));
                Storage::disk($disk)->move($oldRelative, $newRelative);
            }

            $folderIds = $folder->descendantAndSelfIds();

            Media::query()
                ->where('model_type', MediaManager::class)
                ->whereIn('custom_properties->folder_id', $folderIds)
                ->get()
                ->each(fn(Media $item) => $pathSync->syncDiskPathForMedia($item));
        }

        return response()->json([
            'message' => 'Folder renamed.',
            'data' => $this->folderData($folder),
        ]);
    }

    /**
     * Delete a folder and optionally keep its media at root (keep_media=true).
     *
     * DELETE /media-manager/api/folders/{id}?keep_media=true
     */
    public function destroy(int $id, Request $request, MediaStoragePathSynchronizer $pathSync): JsonResponse
    {
        $folder = Folder::findOrFail($id);
        $keepMedia = filter_var($request->query('keep_media', false), FILTER_VALIDATE_BOOLEAN);
        $folderIds = $folder->descendantAndSelfIds();

        $media = Media::query()
            ->where('model_type', MediaManager::class)
            ->whereIn('custom_properties->folder_id', $folderIds)
            ->get();

        if ($keepMedia) {
            foreach ($media as $item) {
                $props = $item->custom_properties;
                unset($props['folder_id'], $props['folder_name']);
                $item->custom_properties = $props;
                $item->save();
                $pathSync->syncDiskPathForMedia($item);
            }
        } else {
            foreach ($media as $item) {
                $item->delete();
            }

            $disk = config('media-manager.disk', 'public');
            $base = trim(config('media-manager.disk_path', 'media-manager'), '/');
            $relative = $base.'/'.$folder->nestedSlugPath();

            if (Storage::disk($disk)->exists($relative)) {
                Storage::disk($disk)->deleteDirectory($relative);
            }
        }

        $folder->delete();

        return response()->json(['message' => 'Folder deleted.']);
    }

    /**
     * Recursively build a folder tree for the sidebar.
     */
    private function buildTree($folders): array
    {
        return $folders->map(function (Folder $folder) {
            $folder->loadMissing('children');

            return array_merge($this->folderData($folder), [
                'children' => $this->buildTree($folder->children),
            ]);
        })->toArray();
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
