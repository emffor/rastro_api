<?php

namespace App\Http\Resources;

use App\Helpers\AuthHelper;
use App\Models\Anexo;
use App\Models\AnexoRelacionavel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnexoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $anexo = $this->resource instanceof AnexoRelacionavel ? $this->resource->anexo : $this->resource;
        $relacionamentos = $this->resolverRelacionamentos($anexo);

        return [
            'id' => AuthHelper::encryptId($anexo?->id),
            'empresa_id' => AuthHelper::encryptId($anexo?->empresa_id),
            'categoria' => $anexo?->categoria,
            'original_name' => $anexo?->original_name,
            'mime_type' => $anexo?->mime_type,
            'size_bytes' => $anexo?->size_bytes,
            'storage_disk' => $anexo?->storage_disk,
            'hash_arquivo' => $anexo?->hash_arquivo,
            'url' => $anexo?->url,
            'uploaded_by' => $anexo?->uploadedBy ? [
                'id' => AuthHelper::encryptId($anexo->uploadedBy->id),
                'name' => $anexo->uploadedBy->name,
                'email' => $anexo->uploadedBy->email,
            ] : null,
            'relacionamentos' => $relacionamentos,
            'created_at' => optional($anexo?->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => optional($anexo?->updated_at)->format('Y-m-d H:i:s'),
            'deleted_at' => optional($anexo?->deleted_at)->format('Y-m-d H:i:s'),
        ];
    }

    private function resolverRelacionamentos(?Anexo $anexo): array
    {
        if ($this->resource instanceof AnexoRelacionavel) {
            return [$this->montarRelacionamento($this->resource)];
        }

        if (!$anexo || !$anexo->relationLoaded('relacionaveis')) {
            return [];
        }

        return $anexo->relacionaveis
            ->map(fn (AnexoRelacionavel $relacionavel) => $this->montarRelacionamento($relacionavel))
            ->all();
    }

    private function montarRelacionamento(AnexoRelacionavel $relacionavel): array
    {
        return [
            'id' => AuthHelper::encryptId($relacionavel->id),
            'anexo_id' => AuthHelper::encryptId($relacionavel->anexo_id),
            'anexable_type' => $relacionavel->anexable_type,
            'anexable_id' => AuthHelper::encryptId($relacionavel->anexable_id),
            'campo' => $relacionavel->campo,
            'ordem' => $relacionavel->ordem,
            'created_at' => optional($relacionavel->created_at)->format('Y-m-d H:i:s'),
            'anexable' => $relacionavel->relationLoaded('anexable') && $relacionavel->anexable ? [
                'id' => AuthHelper::encryptId($relacionavel->anexable->getKey()),
                'tipo' => class_basename($relacionavel->anexable_type),
            ] : null,
        ];
    }
}
