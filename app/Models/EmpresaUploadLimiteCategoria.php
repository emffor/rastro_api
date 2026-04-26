<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpresaUploadLimiteCategoria extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'empresa_upload_limite_categoria';

    protected $fillable = [
        'empresa_id',
        'categoria_slug',
        'mes_referencia',
        'uploads_usados',
    ];

    protected $casts = [
        'uploads_usados' => 'integer',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(AnexoCategoria::class, 'categoria_slug', 'slug');
    }

    public static function obterOuCriar(string $empresaId, string $categoriaSlug, string $mesReferencia): self
    {
        return self::firstOrCreate([
            'empresa_id' => $empresaId,
            'categoria_slug' => $categoriaSlug,
            'mes_referencia' => $mesReferencia,
        ], [
            'uploads_usados' => 0,
        ]);
    }

    public function podeUpload(?int $limiteMensal = null): bool
    {
        if ($limiteMensal === null) {
            return true;
        }

        return (int) $this->uploads_usados < $limiteMensal;
    }

    public function incrementarUploads(): self
    {
        $this->uploads_usados = (int) $this->uploads_usados + 1;
        $this->save();

        return $this;
    }

    public function decrementarUploads(): self
    {
        $this->uploads_usados = max(0, (int) $this->uploads_usados - 1);
        $this->save();

        return $this;
    }
}
