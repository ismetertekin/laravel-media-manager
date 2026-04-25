<?php

namespace Yazilim360\MediaManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:191'],
            'parent_id' => ['nullable', 'integer', 'exists:media_folders,id'],
        ];
    }
}
