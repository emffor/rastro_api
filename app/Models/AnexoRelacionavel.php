<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AnexoRelacionavel extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $table = 'anexos_relacionaveis';

    protected $fillable = [
        'anexo_id',
        'anexable_type',
        'anexable_id',
        'campo',
        'ordem',
        'created_at',
    ];

    protected $casts = [
        'ordem' => 'integer',
        'created_at' => 'datetime',
    ];

    public function anexo(): BelongsTo
    {
        return $this->belongsTo(Anexo::class);
    }

    public function anexable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePorEntidade(Builder $query, string $anexableType, string $anexableId): Builder
    {
        return $query->where('anexable_type', $anexableType)
            ->where('anexable_id', $anexableId);
    }

    public function scopePorAnexo(Builder $query, string $anexoId): Builder
    {
        return $query->where('anexo_id', $anexoId);
    }
}
