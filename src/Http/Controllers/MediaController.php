<?php

namespace Yazilim360\MediaManager\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Yazilim360\MediaManager\Http\Requests\UploadMediaRequest;
use Yazilim360\MediaManager\Http\Resources\MediaResource;
use Yazilim360\MediaManager\Jobs\GenerateVariantsJob;
use Yazilim360\MediaManager\Models\Folder;
use Yazilim360\MediaManager\Models\MediaManager;
use Yazilim360\MediaManager\Support\ImageEditor;
use Yazilim360\MediaManager\Support\MediaAnalyzer;
use Yazilim360\MediaManager\Support\MediaStoragePathSynchronizer;
use Yazilim360\MediaManager\Support\MediaUsage;
use Yazilim360\MediaManager\Support\SvgGuard;
use Yazilim360\MediaManager\Support\VariantGenerator;
use Yazilim360\MediaManager\Support\VariantSpec;
use Yazilim360\MediaManager\Support\VideoPoster;

class MediaController extends Controller
{
    /** Only media owned by the manager is ever exposed or modified. */
    protected function scope(): Builder
    {
        return Media::query()->where('model_type', MediaManager::class);
    }

    /**
     * List media files with folder, search, type, sort and pagination.
     *
     * GET /media-manager/api/files
     *   folder_id, recursive=1, search, type=image,video, sort=created_at|name|size, dir=asc|desc, page, ids[]
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->scope();

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->input('ids')));
        } else {
            $this->applyFolderFilter($query, $request);
        }

        if ($request->filled('search')) {
            $like = '%'.addcslashes((string) $request->search, '%_\\').'%';
            $query->where(function ($q) use ($like) {
                $q->where('file_name', 'like', $like)->orWhere('name', 'like', $like);
            });
        }

        if ($request->filled('type')) {
            $this->applyTypeFilter($query, (string) $request->type);
        }

        $sort = in_array($request->query('sort'), ['created_at', 'name', 'size'], true) ? $request->query('sort') : 'created_at';
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        // id is the tie-breaker so offset pagination never repeats or skips rows.
        $query->orderBy($sort, $dir)->orderBy('id', $dir);

        $perPage = max(1, min(200, (int) config('media-manager.pagination', config('media-manager.per_page', 30))));
        $media = $query->paginate($perPage);

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

    public function show(int $id): MediaResource
    {
        return new MediaResource($this->scope()->findOrFail($id));
    }

    /**
     * Upload one or more files.
     *
     * Optional: variants (JSON), crops (JSON) – see README.
     */
    public function upload(UploadMediaRequest $request, MediaAnalyzer $analyzer, VariantGenerator $variants, VideoPoster $posters): JsonResponse
    {
        $specs = VariantSpec::parseMany($request->input('variants'));
        $crops = $this->decodeCrops($request->input('crops'));

        $owner = MediaManager::getSingleton();
        $folderId = $request->filled('folder_id') ? (int) $request->folder_id : null;
        $folder = $folderId ? Folder::find($folderId) : null;
        $duplicatesMode = config('media-manager.duplicates', 'warn');
        $disk = config('media-manager.disk', 'public');

        $uploaded = [];
        $duplicates = [];
        $warnings = [];

        try {
            foreach ($request->file('files') as $file) {
                $mime = (string) $file->getMimeType();

                if ($mime === 'image/svg+xml' && ! (config('media-manager.allow_svg') && SvgGuard::isSafe($file->getRealPath()))) {
                    throw ValidationException::withMessages(['files' => 'SVG files are not allowed.']);
                }

                $hash = sha1_file($file->getRealPath());
                $existing = $duplicatesMode === 'allow' ? null : $this->scope()->where('custom_properties->hash', $hash)->first();

                if ($existing) {
                    $duplicates[] = [
                        'file' => $file->getClientOriginalName(),
                        'existing' => new MediaResource($existing),
                    ];

                    if ($duplicatesMode === 'reuse') {
                        $uploaded[] = new MediaResource($existing);

                        continue;
                    }
                }

                $props = ['hash' => $hash];

                // Probed up front so the poster frame can be picked inside the clip (see MediaManager::registerMediaConversions).
                if (str_starts_with($mime, 'video/')) {
                    $props += $analyzer->videoInfo($file->getRealPath());
                }
                if ($folder) {
                    $props['folder_id'] = $folder->id;
                    $props['folder_name'] = $folder->name;
                }

                $storedAt = now()->subSeconds(2);

                try {
                    $media = $owner->addMedia($file)
                        ->withCustomProperties($props)
                        ->toMediaCollection($this->collectionFor($mime, (string) $request->input('collection', config('media-manager.collection', 'default'))), $disk);
                } catch (\Throwable $e) {
                    // Spatie stores the file and its row first and renders conversions afterwards. When only
                    // that last step failed the upload itself is fine: keep it, report a warning, never a 500.
                    $media = $this->scope()->where('custom_properties->hash', $hash)->where('created_at', '>=', $storedAt)->latest('id')->first();
                    if (! $media) {
                        throw $e;
                    }

                    report($e);
                    $warnings[] = ['file' => $file->getClientOriginalName(), 'code' => 'processing_failed'];
                }

                $analyzer->analyze($media);

                if (str_starts_with($mime, 'video/')) {
                    $posters->generate($media);
                }

                if ($specs !== [] && ImageEditor::supports($mime)) {
                    // The upload itself succeeded; a failing variant render must not turn it into an error.
                    try {
                        $this->generateVariants($media, $specs, $crops, $variants);
                    } catch (\Throwable $e) {
                        report($e);
                        $media->setCustomProperty('variants_status', 'failed');
                        $media->setCustomProperty('variant_specs', array_map(fn (VariantSpec $s) => $s->toArray(), $specs));
                        $media->save();
                    }
                }

                $uploaded[] = new MediaResource($media->fresh());
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('[MediaManager] Upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'message' => app()->hasDebugModeEnabled() ? 'Upload failed: '.$e->getMessage() : 'Upload failed.',
            ], 500);
        }

        return response()->json([
            'message' => 'Uploaded successfully',
            'data' => $uploaded,
            'duplicates' => $duplicates,
            'warnings' => $warnings,
        ], 201);
    }

    /**
     * Update editable metadata (name, alt, title, description).
     *
     * PATCH /media-manager/api/files/{id}
     */
    public function update(Request $request, int $id): MediaResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'alt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $media = $this->scope()->findOrFail($id);

        if (array_key_exists('name', $data)) {
            $media->name = $data['name'];
        }

        foreach (['alt', 'title', 'description'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] === null || $data[$field] === ''
                    ? $media->forgetCustomProperty($field)
                    : $media->setCustomProperty($field, $data[$field]);
            }
        }

        $media->save();

        return new MediaResource($media);
    }

    /**
     * (Re)generate theme variants, optionally with user supplied crops.
     *
     * POST /media-manager/api/files/{id}/variants   { variants?, crops? }
     * Without `variants` the specs stored on the media (or the config default) are used.
     */
    public function variants(Request $request, int $id, VariantGenerator $generator): MediaResource
    {
        $media = $this->scope()->findOrFail($id);

        if (! ImageEditor::supports($media->mime_type)) {
            throw ValidationException::withMessages(['file' => 'This file type has no image variants.']);
        }

        $specs = VariantSpec::parseMany(
            $request->input('variants')
                ?: $media->getCustomProperty('variant_specs')
                ?: config('media-manager.default_variants', [])
        );

        if ($specs === []) {
            throw ValidationException::withMessages(['variants' => 'No variants requested.']);
        }

        $crops = $this->decodeCrops($request->input('crops'));

        try {
            $this->generateVariants($media, $specs, $crops, $generator, forceSync: true);
        } catch (\Throwable $e) {
            Log::error('[MediaManager] Variant generation failed', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Could not generate image variants.'], 500);
        }

        return new MediaResource($media->fresh());
    }

    /**
     * Set a video's poster (thumbnail) from an image, e.g. a frame the browser grabbed from the video itself.
     * This is what makes ffmpeg optional on the server.
     *
     * POST /media-manager/api/files/{id}/poster   poster (image), second?, duration?, width?, height?
     */
    public function poster(Request $request, int $id, VideoPoster $posters): MediaResource
    {
        $request->validate([
            'poster' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'second' => ['nullable', 'numeric', 'min:0', 'max:86400'],
            'duration' => ['nullable', 'numeric', 'min:0', 'max:86400'],
            'width' => ['nullable', 'integer', 'min:1', 'max:16000'],
            'height' => ['nullable', 'integer', 'min:1', 'max:16000'],
        ]);

        $media = $this->scope()->findOrFail($id);

        if (! str_starts_with((string) $media->mime_type, 'video/')) {
            throw ValidationException::withMessages(['poster' => 'Posters can only be set on videos.']);
        }

        try {
            $ok = $posters->storeFile($media, $request->file('poster')->getRealPath(), $request->filled('second') ? (float) $request->second : null, 'browser');
        } catch (\Throwable $e) {
            report($e);
            $ok = false;
        }

        if (! $ok) {
            throw ValidationException::withMessages(['poster' => 'The image could not be processed.']);
        }

        // Without ffprobe the browser is the only source of the clip's duration and size.
        $media->refresh();
        foreach (['duration', 'width', 'height'] as $key) {
            if ($request->filled($key) && ! $media->getCustomProperty($key)) {
                $media->setCustomProperty($key, $key === 'duration' ? round((float) $request->$key, 2) : (int) $request->$key);
            }
        }
        $media->save();

        return new MediaResource($media->fresh());
    }

    /**
     * Delete one media item. Refused (409) when the usage resolver reports usages
     * unless `force=1` is sent.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $media = $this->scope()->findOrFail($id);
        $usages = MediaUsage::for($media);

        if ($usages !== [] && ! $request->boolean('force')) {
            return response()->json(['message' => 'Media is in use.', 'usages' => [$media->id => $usages]], 409);
        }

        $media->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Delete several items at once.
     *
     * POST /media-manager/api/files/delete { ids: [], force? }
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer']]);

        $items = $this->scope()->whereIn('id', $request->ids)->get();
        $blocked = [];

        foreach ($items as $media) {
            $usages = MediaUsage::for($media);
            if ($usages !== [] && ! $request->boolean('force')) {
                $blocked[$media->id] = $usages;
            }
        }

        if ($blocked !== []) {
            return response()->json(['message' => 'Some media are in use.', 'usages' => $blocked], 409);
        }

        $items->each->delete();

        return response()->json(['success' => true, 'deleted' => $items->pluck('id')]);
    }

    /**
     * Move media items to another folder (null = root).
     */
    public function move(Request $request, MediaStoragePathSynchronizer $pathSync)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
            'folder_id' => 'nullable|integer|exists:media_folders,id',
        ]);

        $folder = $request->folder_id ? Folder::findOrFail($request->folder_id) : null;
        $items = $this->scope()->whereIn('id', $request->ids)->get();

        DB::transaction(function () use ($items, $folder, $pathSync) {
            foreach ($items as $media) {
                $this->place($media, $folder, $pathSync);
            }
        });

        return MediaResource::collection($items);
    }

    /**
     * Copy media items to another folder (null = root).
     */
    public function copy(Request $request, MediaStoragePathSynchronizer $pathSync, VariantGenerator $variants)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
            'folder_id' => 'nullable|integer|exists:media_folders,id',
        ]);

        $folder = $request->folder_id ? Folder::findOrFail($request->folder_id) : null;
        $originals = $this->scope()->whereIn('id', $request->ids)->get();
        $copies = collect();

        foreach ($originals as $original) {
            $copy = $original->copy($original->model, $original->collection_name, $original->disk);

            // Variant files live next to the original; rebuild them for the copy.
            $specs = (array) $copy->getCustomProperty('variant_specs', []);
            $crops = (array) $copy->getCustomProperty('crops', []);
            foreach (['variants', 'variants_status', 'crops', 'variant_specs'] as $key) {
                $copy->forgetCustomProperty($key);
            }
            $copy->save();

            $this->place($copy, $folder, $pathSync);

            if ($specs !== [] && ImageEditor::supports($copy->mime_type)) {
                try {
                    $variants->generate($copy, VariantSpec::parseMany($specs), $crops);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            $copies->push($copy->fresh());
        }

        return MediaResource::collection($copies);
    }

    // ─── helpers ───────────────────────────────────────────────

    protected function place(Media $media, ?Folder $folder, MediaStoragePathSynchronizer $pathSync): void
    {
        $oldDir = $pathSync->expectedDirectory($media);

        if ($folder) {
            $media->setCustomProperty('folder_id', $folder->id);
            $media->setCustomProperty('folder_name', $folder->name);
        } else {
            $media->forgetCustomProperty('folder_id');
            $media->forgetCustomProperty('folder_name');
        }

        $media->save();
        $pathSync->relocate($media, $oldDir);
    }

    protected function applyFolderFilter(Builder $query, Request $request): void
    {
        $recursive = $request->boolean('recursive');

        if ($request->filled('folder_id')) {
            $folderId = (int) $request->folder_id;
            $ids = $recursive ? (Folder::find($folderId)?->descendantAndSelfIds() ?? [$folderId]) : [$folderId];
            $query->whereIn('custom_properties->folder_id', $ids);

            return;
        }

        if ($recursive) {
            return; // everything
        }

        // Root view: no folder_id key, or a legacy JSON null (MySQL treats it as the string "null").
        $query->where(function ($q) {
            $q->whereNull('custom_properties->folder_id')
                ->orWhere('custom_properties->folder_id', 'null');
        });
    }

    protected function applyTypeFilter(Builder $query, string $type): void
    {
        $types = array_filter(array_map('trim', explode(',', $type)));

        $query->where(function ($q) use ($types) {
            foreach ($types as $t) {
                match ($t) {
                    'image' => $q->orWhere('mime_type', 'like', 'image/%'),
                    'video' => $q->orWhere('mime_type', 'like', 'video/%'),
                    'pdf' => $q->orWhere('mime_type', 'application/pdf'),
                    'document', 'file' => $q->orWhere(fn ($d) => $d
                        ->where('mime_type', 'not like', 'image/%')
                        ->where('mime_type', 'not like', 'video/%')),
                    default => null,
                };
            }
        });
    }

    protected function collectionFor(string $mime, string $default): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'images',
            str_starts_with($mime, 'video/') => 'videos',
            in_array($mime, ['application/pdf', 'text/plain', 'application/zip'], true) => 'documents',
            default => $default,
        };
    }

    /**
     * @param  list<VariantSpec>  $specs
     */
    protected function generateVariants(Media $media, array $specs, array $crops, VariantGenerator $generator, bool $forceSync = false): void
    {
        if (config('media-manager.queue') && ! $forceSync) {
            $media->setCustomProperty('variants_status', 'pending');
            $media->setCustomProperty('variant_specs', array_map(fn (VariantSpec $s) => $s->toArray(), $specs));
            $media->save();

            GenerateVariantsJob::dispatch($media, array_map(fn (VariantSpec $s) => $s->toArray(), $specs), $crops);

            return;
        }

        $generator->generate($media, $specs, $crops);
    }

    protected function decodeCrops(mixed $input): array
    {
        if (is_string($input)) {
            $input = $input === '' ? [] : json_decode($input, true);
        }

        if ($input !== null && ! is_array($input)) {
            throw ValidationException::withMessages(['crops' => 'Invalid crops payload.']);
        }

        return $input ?? [];
    }
}
