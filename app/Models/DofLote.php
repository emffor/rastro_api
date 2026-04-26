<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DofLote extends Model
{
    use HasFactory, HasUuids, BelongsToEmpresa;

    protected $table = 'dof_lotes';

    protected $fillable = [
        'dof_id',
        'dof_item_id',
        'lote_id',
        'volume_m3',
        'observacao',
        'empresa_id',
    ];

    protected $casts = [
        'volume_m3' => 'decimal:4',
    ];

    public function dof(): BelongsTo
    {
        return $this->belongsTo(Dof::class);
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    public function dofItem(): BelongsTo
    {
        return $this->belongsTo(DofItem::class);
    }

    public function alocacao(): HasOne
    {
        return $this->hasOne(DofAlocacao::class);
    }
}
