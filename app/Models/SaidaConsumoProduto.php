<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaidaConsumoProduto extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'saida_consumo_produtos';

    protected $fillable = [
        'saida_consumo_id',
        'saida_operacao_item_id',
        'produto_dimensionado_id',
        'quantidade_pecas',
        'volume_unitario_m3',
        'volume_total_m3',
        'produto_nome_snapshot',
    ];

    protected $casts = [
        'quantidade_pecas' => 'integer',
        'volume_unitario_m3' => 'decimal:6',
        'volume_total_m3' => 'decimal:4',
    ];

    public function saidaConsumo(): BelongsTo
    {
        return $this->belongsTo(SaidaConsumo::class);
    }

    public function saidaOperacaoItem(): BelongsTo
    {
        return $this->belongsTo(SaidaOperacaoItem::class);
    }

    public function produtoDimensionado(): BelongsTo
    {
        return $this->belongsTo(ProdutoDimensionado::class);
    }
}
