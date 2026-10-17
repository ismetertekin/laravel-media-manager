<?php

namespace Yazilim360\MediaManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:191', 'regex:/^[^\/\\\\:*?"<>|]+$/u',
                Rule::unique('media_folders', 'name')->where(
                    fn ($q) => $q->where('parent_id', $this->input('parent_id'))
                ),
            ],
            'parent_id' => ['nullable', 'integer', 'exists:media_folders,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A folder with this name already exists here.',
            'name.regex' => 'Folder name contains invalid characters.',
        ];
    }
}
