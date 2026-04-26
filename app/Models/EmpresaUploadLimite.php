<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpresaUploadLimite extends Model
{
    use HasFactory, HasUuids;

    public const LIMITE_MENSAL = 10;

    protected $fillable = [
        'empresa_id',
        'mes_referencia',
        'uploads_nf',
        'uploads_dof',
    ];

    protected $casts = [
        'uploads_nf' => 'integer',
        'uploads_dof' => 'integer',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public static function obterOuCriar(string $empresaId, string $mesReferencia): self
    {
        return self::firstOrCreate([
            'empresa_id' => $empresaId,
            'mes_referencia' => $mesReferencia,
        ], [
            'uploads_nf' => 0,
            'uploads_dof' => 0,
        ]);
    }

    public function podeUploadNf(): bool
    {
        return $this->uploads_nf < self::LIMITE_MENSAL;
    }

    public function podeUploadDof(): bool
    {
        return $this->uploads_dof < self::LIMITE_MENSAL;
    }

    public function incrementarNf(): self
    {
        $this->uploads_nf = (int) $this->uploads_nf + 1;
        $this->save();

        return $this;
    }

    public function incrementarDof(): self
    {
        $this->uploads_dof = (int) $this->uploads_dof + 1;
        $this->save();

        return $this;
    }

    public function decrementarNf(): self
    {
        $this->uploads_nf = max(0, (int) $this->uploads_nf - 1);
        $this->save();

        return $this;
    }

    public function decrementarDof(): self
    {
        $this->uploads_dof = max(0, (int) $this->uploads_dof - 1);
        $this->save();

        return $this;
    }
}
