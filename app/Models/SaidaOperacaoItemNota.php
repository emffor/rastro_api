<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SaidaOperacaoItemNota extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'saida_operacao_item_notas';

    protected $fillable = [
        'saida_operacao_item_id',
        'numero_nf',
        'data_emissao_nf',
    ];

    protected $casts = [
        'data_emissao_nf' => 'date',
    ];

    public function saidaOperacaoItem(): BelongsTo
    {
        return $this->belongsTo(SaidaOperacaoItem::class);
    }

    public function anexosRelacionaveis(): MorphMany
    {
        return $this->morphMany(AnexoRelacionavel::class, 'anexable');
    }
}
