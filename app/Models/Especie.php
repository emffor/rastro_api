<?php

namespace App\Models;

use App\Support\ProdutoDimensionadoEspecieMatcher;
use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Especie extends Model
{
    use HasFactory, HasUuids, BelongsToEmpresa;

    protected $table = 'especies';

    protected $fillable = [
        'nome_cientifico',
        'nome_popular',
        'tipo_serragem_id',
        'tipo',
        'nome_tipo',
        'nome_formatado',
        'empresa_id',
    ];

    protected static function booted(): void
    {
        static::saving(function (Especie $especie) {
            $tipoCanonico = $especie->resolverTipoSerragemNome();
            $especie->tipo = $tipoCanonico;
            $especie->nome_tipo = ProdutoDimensionadoEspecieMatcher::normalizarNomeTipoDescricao(
                $especie->nome_tipo,
                $tipoCanonico
            );
            $especie->nome_formatado = $especie->gerarNomeFormatado();
        });
    }

    public function gerarNomeFormatado(): string
    {
        $parts = [];

        $nomeTipo = trim((string) ($this->nome_tipo ?? ''));
        if ($nomeTipo !== '') {
            $parts[] = $nomeTipo;
        }

        $nomesParts = [];
        if ($this->nome_cientifico) {
            $nomesParts[] = $this->nome_cientifico;
        }
        if ($this->nome_popular) {
            $nomesParts[] = $this->nome_popular;
        }

        if (count($nomesParts) > 0) {
            $parts[] = implode(' - ', $nomesParts);
        }

        return implode(' / ', $parts) ?: ($this->nome_popular ?? '');
    }

    public function resolverTipoSerragemNome(): string
    {
        if ($this->relationLoaded('tipoSerragem') && $this->tipoSerragem) {
            return ProdutoDimensionadoEspecieMatcher::normalizarTipo($this->tipoSerragem->nome);
        }

        if ($this->tipo_serragem_id) {
            $tipo = TipoSerragem::query()->find($this->tipo_serragem_id);
            if ($tipo) {
                return ProdutoDimensionadoEspecieMatcher::normalizarTipo($tipo->nome);
            }
        }

        return ProdutoDimensionadoEspecieMatcher::normalizarTipoEspecie(
            $this->tipo,
            $this->nome_tipo
        );
    }

    public function dofItens()
    {
        return $this->hasMany(DofItem::class);
    }

    public function tipoSerragem(): BelongsTo
    {
        return $this->belongsTo(TipoSerragem::class);
    }

    public function produtosDimensionadosVinculados(): BelongsToMany
    {
        return $this->belongsToMany(
            ProdutoDimensionado::class,
            'produto_dimensionado_especies',
            'especie_id',
            'produto_dimensionado_id'
        )->withPivot(['empresa_id', 'origem_vinculo'])->withTimestamps();
    }
}
