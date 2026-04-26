<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lote extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'lotes';

    protected $fillable = [
        'patio_id',
        'codigo',
        'nome',
        'descricao',
        'pos_x',
        'pos_y',
        'largura',
        'altura',
        'rotacao',
        'cor',
        'cor_borda',
        'status',
        'capacidade_volume',
        'volume_ocupado',
        'largura_metros',
        'comprimento_metros',
        'altura_metros',
    ];

    protected $casts = [
        'pos_x' => 'decimal:2',
        'pos_y' => 'decimal:2',
        'largura' => 'decimal:2',
        'altura' => 'decimal:2',
        'rotacao' => 'decimal:2',
        'capacidade_volume' => 'decimal:4',
        'volume_ocupado' => 'decimal:4',
        'largura_metros' => 'decimal:2',
        'comprimento_metros' => 'decimal:2',
        'altura_metros' => 'decimal:2',
    ];

    public function patio(): BelongsTo
    {
        return $this->belongsTo(Patio::class);
    }

    public function dofLotes(): HasMany
    {
        return $this->hasMany(DofLote::class);
    }

    public function dofAlocacoes(): HasMany
    {
        return $this->hasMany(DofAlocacao::class);
    }

    public function movimentacoesOrigem(): HasMany
    {
        return $this->hasMany(Movimentacao::class, 'lote_origem_id');
    }

    public function movimentacoesDestino(): HasMany
    {
        return $this->hasMany(Movimentacao::class, 'lote_destino_id');
    }

    public function recalcularVolumeOcupado(): void
    {
        $this->volume_ocupado = $this->dofLotes()->sum('volume_m3');
        $this->atualizarStatus();
        $this->save();
    }

    public function atualizarStatus(): void
    {
        // Preserva bloqueio manual do lote.
        if ($this->status === 'BLOQUEADO') {
            return;
        }
        $volumeOcupado = (float) $this->volume_ocupado;
        // Preserva reserva manual quando ainda não há volume alocado.
        if ($this->status === 'RESERVADO' && $volumeOcupado <= 0) {
            return;
        }

        $this->status = match (true) {
            $volumeOcupado <= 0 => 'DISPONIVEL',
            $this->capacidade_volume && $volumeOcupado >= (float) $this->capacidade_volume => 'OCUPADO',
            default => 'RESERVADO',
        };
    }

    public function getPercentualOcupacaoAttribute(): float
    {
        if (!$this->capacidade_volume || (float) $this->capacidade_volume <= 0) {
            return 0;
        }
        return min(100, ((float) $this->volume_ocupado / (float) $this->capacidade_volume) * 100);
    }

    public function getCorStatusAttribute(): string
    {
        return match ($this->status) {
            'DISPONIVEL' => '#4CAF50',
            'OCUPADO' => '#F44336',
            'RESERVADO' => '#FF9800',
            'BLOQUEADO' => '#9E9E9E',
            default => '#FFFFFF',
        };
    }
}
