<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DofItem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'dof_itens';

    protected $fillable = [
        'dof_id',
        'especie_id',
        'tipo',
        'quantidade_autorizada',
        'quantidade_disponivel',
    ];

    protected $casts = [
        'quantidade_autorizada' => 'decimal:4',
        'quantidade_disponivel' => 'decimal:4',
    ];

    public function dof(): BelongsTo
    {
        return $this->belongsTo(Dof::class);
    }

    public function especie(): BelongsTo
    {
        return $this->belongsTo(Especie::class);
    }

    public function dofLotes(): HasMany
    {
        return $this->hasMany(DofLote::class);
    }

    public function dofAlocacoes(): HasMany
    {
        return $this->hasMany(DofAlocacao::class);
    }
}
