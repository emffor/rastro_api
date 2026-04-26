<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaidaConsumo extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'saida_consumos';

    protected $fillable = [
        'saida_operacao_item_id',
        'dof_id',
        'dof_item_id',
        'dof_lote_id',
        'lote_id',
        'volume_m3',
    ];

    protected $casts = [
        'volume_m3' => 'decimal:4',
    ];

    public function saidaOperacaoItem(): BelongsTo
    {
        return $this->belongsTo(SaidaOperacaoItem::class);
    }

    public function dof(): BelongsTo
    {
        return $this->belongsTo(Dof::class);
    }

    public function dofItem(): BelongsTo
    {
        return $this->belongsTo(DofItem::class);
    }

    public function dofLote(): BelongsTo
    {
        return $this->belongsTo(DofLote::class);
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    public function consumoProdutos(): HasMany
    {
        return $this->hasMany(SaidaConsumoProduto::class);
    }
}
