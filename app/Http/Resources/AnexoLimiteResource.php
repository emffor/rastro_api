<?php

namespace App\Http\Resources;

use App\Models\EmpresaUploadLimite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnexoLimiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $limiteMensal = EmpresaUploadLimite::LIMITE_MENSAL;
        $uploadsNf = (int) $this->uploads_nf;
        $uploadsDof = (int) $this->uploads_dof;

        return [
            'uploads_nf_usados' => $uploadsNf,
            'uploads_dof_usados' => $uploadsDof,
            'uploads_nf_restantes' => max(0, $limiteMensal - $uploadsNf),
            'uploads_dof_restantes' => max(0, $limiteMensal - $uploadsDof),
            'uploads_nf_percentual' => min(100, round(($uploadsNf / $limiteMensal) * 100, 2)),
            'uploads_dof_percentual' => min(100, round(($uploadsDof / $limiteMensal) * 100, 2)),
            'mes_referencia' => $this->mes_referencia,
        ];
    }
}
