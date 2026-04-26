<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaidaOperacaoItem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'saida_operacao_itens';

    protected $fillable = [
        'saida_operacao_id',
        'especie_id',
        'volume_solicitado_m3',
        'volume_baixado_m3',
        'volume_sem_produto_m3',
        'observacao',
    ];

    protected $casts = [
        'volume_solicitado_m3' => 'decimal:4',
        'volume_baixado_m3' => 'decimal:4',
        'volume_sem_produto_m3' => 'decimal:4',
    ];

    public function saidaOperacao(): BelongsTo
    {
        return $this->belongsTo(SaidaOperacao::class);
    }

    public function especie(): BelongsTo
    {
        return $this->belongsTo(Especie::class);
    }

    public function notasFiscais(): HasMany
    {
        return $this->hasMany(SaidaOperacaoItemNota::class);
    }

    public function consumos(): HasMany
    {
        return $this->hasMany(SaidaConsumo::class);
    }

    public function consumoProdutos(): HasMany
    {
        return $this->hasMany(SaidaConsumoProduto::class);
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(Movimentacao::class, 'saida_operacao_item_id');
    }
}
