<?php

namespace App\Models;

use App\Support\ProdutoDimensionadoEspecieMatcher;
use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProdutoDimensionado extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToEmpresa;

    protected $table = 'produtos_dimensionados';

    protected $fillable = [
        'codigo',
        'empresa_id',
        'especie_id',
        'tipo_dof',
        'nome',
        'nome_concatenado',
        'espessura_cm',
        'largura_cm',
        'comprimento_m',
        'volume_unitario_m3',
        'observacao',
        'ativo',
    ];

    protected $casts = [
        'espessura_cm' => 'decimal:2',
        'largura_cm' => 'decimal:2',
        'comprimento_m' => 'decimal:2',
        'volume_unitario_m3' => 'decimal:6',
        'ativo' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProdutoDimensionado $produto): void {
            if (empty($produto->codigo)) {
                $produto->codigo = self::gerarCodigoUnico();
            }
        });

        static::saving(function (ProdutoDimensionado $produto): void {
            $produto->tipo_dof = self::normalizarTipo($produto->tipo_dof);
            $produto->volume_unitario_m3 = $produto->calcularVolumeUnitario();
        });
    }

    private static function gerarCodigoUnico(): string
    {
        $maxTentativas = 10;
        
        for ($i = 0; $i < $maxTentativas; $i++) {
            $codigo = 'PD-' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            
            if (!self::where('codigo', $codigo)->exists()) {
                return $codigo;
            }
        }
        
        throw new \RuntimeException('Não foi possível gerar um código único após ' . $maxTentativas . ' tentativas.');
    }

    public static function normalizarTipo(?string $tipo): string
    {
        return ProdutoDimensionadoEspecieMatcher::normalizarTipo($tipo);
    }

    public static function normalizarTexto(?string $texto): string
    {
        return ProdutoDimensionadoEspecieMatcher::normalizarTexto($texto);
    }

    public static function tipoDaEspecie(?string $nomeTipo): string
    {
        return ProdutoDimensionadoEspecieMatcher::tipoDaEspecie($nomeTipo);
    }

    public function calcularVolumeUnitario(): float
    {
        $espessura = (float) $this->espessura_cm;
        $largura = (float) $this->largura_cm;
        $comprimento = (float) $this->comprimento_m;

        if ($espessura <= 0 || $largura <= 0 || $comprimento <= 0) {
            return 0.0;
        }

        return round(($espessura / 100) * ($largura / 100) * $comprimento, 6);
    }

    public function especie(): BelongsTo
    {
        return $this->belongsTo(Especie::class);
    }

    public function alocacaoLinhas(): HasMany
    {
        return $this->hasMany(DofAlocacaoLinha::class);
    }

    public function especiesVinculadas(): BelongsToMany
    {
        return $this->belongsToMany(
            Especie::class,
            'produto_dimensionado_especies',
            'produto_dimensionado_id',
            'especie_id'
        )->withPivot(['empresa_id', 'origem_vinculo'])->withTimestamps();
    }
}
