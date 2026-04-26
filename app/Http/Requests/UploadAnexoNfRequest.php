<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadAnexoNfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'arquivo' => ['required', 'file', 'mimes:pdf', 'max:500'],
        ];
    }
}
