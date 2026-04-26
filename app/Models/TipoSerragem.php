<?php

namespace App\Models;

use App\Support\ProdutoDimensionadoEspecieMatcher;
use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TipoSerragem extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToEmpresa;

    public const TIPOS_PADRAO = [
        'CAVACO',
        'CAIBRO',
        'VIGA',
        'RIPA',
        'SARRAFO',
        'TABUA',
        'PRANCHA',
        'TORA',
        'LENHA',
        'CARVAO',
        'ESTACA',
    ];

    protected $table = 'tipos_serragem';

    protected $fillable = [
        'empresa_id',
        'nome',
    ];

    protected static function booted(): void
    {
        static::saving(function (TipoSerragem $tipoSerragem): void {
            $tipoSerragem->nome = ProdutoDimensionadoEspecieMatcher::normalizarTipo($tipoSerragem->nome);
        });
    }

    public function especies(): HasMany
    {
        return $this->hasMany(Especie::class);
    }
}
