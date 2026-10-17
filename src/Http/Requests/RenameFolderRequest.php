<?php

namespace Yazilim360\MediaManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Yazilim360\MediaManager\Models\Folder;

class RenameFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $parentId = Folder::query()->whereKey($this->input('id'))->value('parent_id');

        return [
            'id' => ['required', 'integer', 'exists:media_folders,id'],
            'name' => [
                'required', 'string', 'max:191', 'regex:/^[^\/\\\\:*?"<>|]+$/u',
                Rule::unique('media_folders', 'name')
                    ->where(fn ($q) => $q->where('parent_id', $parentId))
                    ->ignore($this->input('id')),
            ],
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
