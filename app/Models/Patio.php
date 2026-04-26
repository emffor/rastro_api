<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Traits\BelongsToEmpresa;

class Patio extends Model
{
    use HasFactory, SoftDeletes, HasUuids, BelongsToEmpresa;

    protected $table = 'patios';

    protected $fillable = [
        'empresa_id',
        'nome',
        'descricao',
        'endereco',
        'largura',
        'altura',
        'cor_fundo',
        'configuracao_mapa',
        'ativo',
        'largura_metros',
        'comprimento_metros',
        'altura_metros',
    ];

    protected $casts = [
        'largura' => 'decimal:2',
        'altura' => 'decimal:2',
        'configuracao_mapa' => 'array',
        'ativo' => 'boolean',
        'largura_metros' => 'decimal:2',
        'comprimento_metros' => 'decimal:2',
        'altura_metros' => 'decimal:2',
    ];

    public function lotes()
    {
        return $this->hasMany(Lote::class);
    }

    public function lotesAtivos()
    {
        return $this->hasMany(Lote::class)->whereNull('deleted_at');
    }

    public function areasBloqueadas()
    {
        return $this->hasMany(PatioAreaBloqueada::class);
    }

    public function getVolumeOcupadoTotalAttribute(): float
    {
        return (float) $this->lotes()->sum('volume_ocupado');
    }

    public function getQuantidadeLotesAttribute(): int
    {
        return $this->lotes()->count();
    }
}
