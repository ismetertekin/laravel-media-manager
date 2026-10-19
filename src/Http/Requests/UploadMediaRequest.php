<?php

namespace Yazilim360\MediaManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = (int) config('media-manager.max_upload', 51200);
        $allowed = (array) config('media-manager.allowed_types', []);

        if (config('media-manager.allow_svg')) {
            $allowed[] = 'image/svg+xml';
        } else {
            $allowed = array_values(array_diff($allowed, ['image/svg+xml']));
        }

        // Common variations
        if (in_array('image/png', $allowed, true)) {
            $allowed[] = 'image/x-png';
        }

        return [
            'files' => ['required', 'array'],
            'files.*' => [
                'file',
                "max:{$maxSize}",
                'mimetypes:'.implode(',', array_unique($allowed)),
            ],
            'folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
            'collection' => ['nullable', 'string', 'max:191'],
            'variants' => ['nullable'],
            'crops' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'files.*.mimetypes' => 'One or more files have an invalid type.',
            'files.*.uploaded' => 'The file could not be uploaded. The PHP limits (upload_max_filesize / post_max_size) may be lower than the configured maximum.',
            'files.*.max' => 'One or more files exceeds the size limit.',
        ];
    }
}
