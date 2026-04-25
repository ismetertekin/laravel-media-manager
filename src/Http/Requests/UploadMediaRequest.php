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
        $maxSize = config('media-manager.max_upload', 51200);
        $allowedMimes = config('media-manager.allowed_types', [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'video/mp4', 'video/webm', 'application/pdf', 'text/plain'
        ]);

        // Add common variations
        if (in_array('image/png', $allowedMimes)) $allowedMimes[] = 'image/x-png';
        
        $mimeTypes = implode(',', $allowedMimes);

        return [
            'files' => ['required', 'array'],
            'files.*' => [
                'file',
                "max:{$maxSize}",
                "mimetypes:{$mimeTypes}",
            ],
            'folder_id'   => ['nullable', 'integer', 'exists:media_folders,id'],
            'collection'  => ['nullable', 'string', 'max:191'],
        ];
    }

    public function messages(): array
    {
        return [
            'files.*.mimetypes' => 'One or more files have an invalid type.',
            'files.*.max'       => 'One or more files exceeds the size limit.',
        ];
    }
}
