<?php

namespace App\Http\Resources;

use App\Helpers\AuthHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnexoCategoriaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => AuthHelper::encryptId($this->id),
            'slug' => $this->slug,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'ativo' => (bool) $this->ativo,
            'limite_mensal_por_empresa' => $this->limite_mensal_por_empresa,
            'tamanho_max_kb' => $this->tamanho_max_kb,
            'mime_types_permitidos' => $this->mime_types_permitidos ?: ['application/pdf'],
            'created_at' => optional($this->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => optional($this->updated_at)->format('Y-m-d H:i:s'),
        ];
    }
}
