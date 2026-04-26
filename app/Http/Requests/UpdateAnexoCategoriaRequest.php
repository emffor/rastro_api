<?php

namespace App\Http\Requests;

use App\Helpers\AuthHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnexoCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoriaId = AuthHelper::decryptId((string) $this->route('id')) ?? $this->route('id');

        return [
            'slug' => ['sometimes', 'string', 'max:100', Rule::unique('anexo_categorias', 'slug')->ignore($categoriaId)],
            'nome' => ['sometimes', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'ativo' => ['sometimes', 'boolean'],
            'limite_mensal_por_empresa' => ['nullable', 'integer', 'min:1'],
            'tamanho_max_kb' => ['nullable', 'integer', 'min:1'],
            'mime_types_permitidos' => ['nullable', 'array'],
            'mime_types_permitidos.*' => ['string', 'max:150'],
        ];
    }
}
