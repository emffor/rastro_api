<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteAnexoGenericoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'acao' => ['required', 'string', 'in:remocao,substituicao'],
            'observacao' => ['required', 'string', 'max:500'],
        ];
    }
}
