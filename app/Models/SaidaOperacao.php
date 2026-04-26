<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaidaOperacao extends Model
{
    use HasFactory, HasUuids, BelongsToEmpresa;

    protected $table = 'saida_operacoes';

    protected $fillable = [
        'empresa_id',
        'usuario_id',
        'observacao',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(SaidaOperacaoItem::class);
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(Movimentacao::class, 'saida_operacao_id');
    }
}
