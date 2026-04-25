<?php

namespace Yazilim360\MediaManager\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Yazilim360\MediaManager\Http\Requests\UploadMediaRequest;
use Yazilim360\MediaManager\Http\Resources\MediaResource;
use Yazilim360\MediaManager\Models\Folder;
use Yazilim360\MediaManager\Models\MediaManager;
use Yazilim360\MediaManager\Support\MediaStoragePathSynchronizer;

class MediaController extends Controller
{
    /**
     * List media files with optional folder filter, search, and pagination.
     *
     * GET /media-manager/api/files?folder_id=&search=&page=
     */
    public function index(Request $request): JsonResponse
    {
        $query = Media::query()
            ->where('model_type', MediaManager::class);

        // Filter by virtual folder ID (stored in custom_properties)
        if ($request->filled('folder_id')) {
            $folderId = (int)$request->folder_id;
            $query->where('custom_properties->folder_id', $folderId);
        } else {
            // Root view: only files not assigned to a folder
            $query->where(function ($q) {
                $q->whereNull('custom_properties->folder_id');
            });
        }

        // Search by file name
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('file_name', 'like', $search)
                    ->orWhere('name', 'like', $search);
            });
        }

        // Filter by mime type category
        if ($request->filled('type')) {
            match ($request->type) {
                'image' => $query->where('mime_type', 'like', 'image/%'),
                'video' => $query->where('mime_type', 'like', 'video/%'),
                'document' => $query->where('mime_type', 'like', 'application/%'),
                default => null,
            };
        }

        $perPage = (int) config('media-manager.pagination', config('media-manager.per_page', 30));
        $media = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => MediaResource::collection($media->items()),
            'meta' => [
                'current_page' => $media->currentPage(),
                'last_page' => $media->lastPage(),
                'per_page' => $media->perPage(),
                'total' => $media->total(),
            ],
        ]);
    }

    /**
     * Upload a new file(s) into the media manager.
     */
    public function upload(UploadMediaRequest $request): JsonResponse
    {
        try {
            $owner = MediaManager::getSingleton();
            $folderId = $request->folder_id ? (int)$request->folder_id : null;
            $collection = $request->input('collection', config('media-manager.collection', 'default'));
            $disk = config('media-manager.disk', 'public');
            
            $uploadedMedia = [];

            foreach ($request->file('files') as $file) {
                // Automatically route to collection based on mime type
                $mimeType = $file->getMimeType();
                $targetCollection = $collection;
                
                if (str_starts_with($mimeType, 'image/')) {
                    $targetCollection = 'images';
                } elseif (str_starts_with($mimeType, 'video/')) {
                    $targetCollection = 'videos';
                } elseif (in_array($mimeType, ['application/pdf', 'text/plain', 'application/zip'])) {
                    $targetCollection = 'documents';
                }

                $customProperties = ['folder_id' => $folderId];
                if ($folderId) {
                    $folder = Folder::find($folderId);
                    $customProperties['folder_name'] = $folder?->name;
                }

                $media = $owner->addMedia($file)
                    ->withCustomProperties($customProperties)
                    ->toMediaCollection($targetCollection, $disk);
                    
                $uploadedMedia[] = new MediaResource($media);
            }

            return response()->json([
                'message' => 'Uploaded successfully',
                'data'    => $uploadedMedia
            ], 201);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('[MediaManager] Upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Upload failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete a media item.
     */
    public function destroy($id)
    {
        $media = Media::findOrFail($id);
        $media->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Move media items to another folder.
     */
    public function move(Request $request, MediaStoragePathSynchronizer $pathSync)
    {
        $request->validate([
            'ids'       => 'required|array',
            'ids.*'     => 'integer|exists:media,id',
            'folder_id' => 'nullable|integer|exists:media_folders,id',
        ]);

        $folder = null;
        if ($request->folder_id) {
            $folder = Folder::findOrFail($request->folder_id);
        }

        $items = Media::whereIn('id', $request->ids)->get();

        foreach ($items as $media) {
            $customProperties = $media->custom_properties;

            if ($folder) {
                $customProperties['folder_id'] = $folder->id;
                $customProperties['folder_name'] = $folder->name;
            } else {
                unset($customProperties['folder_id'], $customProperties['folder_name']);
            }

            $media->custom_properties = $customProperties;
            $media->save();

            $pathSync->syncDiskPathForMedia($media);
        }

        return MediaResource::collection($items);
    }

    /**
     * Copy media items to another folder.
     */
    public function copy(Request $request, MediaStoragePathSynchronizer $pathSync)
    {
        $request->validate([
            'ids'       => 'required|array',
            'ids.*'     => 'integer|exists:media,id',
            'folder_id' => 'nullable|integer|exists:media_folders,id',
        ]);

        $folder = null;
        if ($request->folder_id) {
            $folder = Folder::findOrFail($request->folder_id);
        }

        $originals = Media::whereIn('id', $request->ids)->get();
        $copies = collect();

        foreach ($originals as $original) {
            $copy = $original->copy($original->model, $original->collection_name, $original->disk);
            $customProperties = $copy->custom_properties;

            if ($folder) {
                $customProperties['folder_id'] = $folder->id;
                $customProperties['folder_name'] = $folder->name;
            } else {
                unset($customProperties['folder_id'], $customProperties['folder_name']);
            }

            $copy->custom_properties = $customProperties;
            $copy->save();

            $pathSync->syncDiskPathForMedia($copy);
            $copies->push($copy);
        }

        return MediaResource::collection($copies);
    }
}
