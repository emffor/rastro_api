<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DofAlocacao extends Model
{
    use HasFactory, HasUuids, BelongsToEmpresa;

    public const MODO_MANUAL = 'MANUAL';
    public const MODO_PECAS = 'PECAS';

    protected $table = 'dof_alocacoes';

    protected $fillable = [
        'empresa_id',
        'dof_id',
        'dof_item_id',
        'lote_id',
        'dof_lote_id',
        'modo_alocacao',
        'volume_total_m3',
        'total_pecas',
        'observacao',
        'usuario_id',
    ];

    protected $casts = [
        'volume_total_m3' => 'decimal:4',
        'total_pecas' => 'integer',
    ];

    public function dof(): BelongsTo
    {
        return $this->belongsTo(Dof::class);
    }

    public function dofItem(): BelongsTo
    {
        return $this->belongsTo(DofItem::class);
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    public function dofLote(): BelongsTo
    {
        return $this->belongsTo(DofLote::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function linhas(): HasMany
    {
        return $this->hasMany(DofAlocacaoLinha::class);
    }
}
