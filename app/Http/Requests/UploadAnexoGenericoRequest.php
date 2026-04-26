<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadAnexoGenericoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf', 'max:500'],
            'categoria_slug' => ['required', 'string', 'max:100'],
            'entidade_type' => ['required', 'string', 'max:255'],
            'entidade_id' => ['required', 'string', 'max:2048'],
            'campo' => ['nullable', 'string', 'max:100'],
            'acao' => ['nullable', 'string', 'in:upload,substituicao'],
            'observacao' => ['nullable', 'string', 'max:500'],
        ];
    }
}
