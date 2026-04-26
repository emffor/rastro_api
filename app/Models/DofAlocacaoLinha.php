<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DofAlocacaoLinha extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'dof_alocacao_linhas';

    protected $fillable = [
        'dof_alocacao_id',
        'produto_dimensionado_id',
        'ordem',
        'quantidade_pecas',
        'volume_unitario_m3',
        'volume_total_m3',
        'produto_nome_snapshot',
        'especie_id_snapshot',
        'tipo_dof_snapshot',
        'espessura_cm_snapshot',
        'largura_cm_snapshot',
        'comprimento_m_snapshot',
    ];

    protected $casts = [
        'ordem' => 'integer',
        'quantidade_pecas' => 'integer',
        'volume_unitario_m3' => 'decimal:6',
        'volume_total_m3' => 'decimal:4',
        'espessura_cm_snapshot' => 'decimal:2',
        'largura_cm_snapshot' => 'decimal:2',
        'comprimento_m_snapshot' => 'decimal:2',
    ];

    public function alocacao(): BelongsTo
    {
        return $this->belongsTo(DofAlocacao::class, 'dof_alocacao_id');
    }

    public function produtoDimensionado(): BelongsTo
    {
        return $this->belongsTo(ProdutoDimensionado::class);
    }
}
