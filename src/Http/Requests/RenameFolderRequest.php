<?php

namespace Yazilim360\MediaManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RenameFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id'   => ['required', 'integer', 'exists:media_folders,id'],
            'name' => ['required', 'string', 'max:191'],
        ];
    }
}
