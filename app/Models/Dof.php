<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Traits\BelongsToEmpresa;
use App\Traits\LogsAdminMasterContext;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dof extends Model
{
    use HasFactory, SoftDeletes, HasUuids, BelongsToEmpresa, LogsActivity, LogsAdminMasterContext;

    public const STATUS_ATIVO = 'ATIVO';
    public const STATUS_PARCIAL = 'PARCIAL';
    public const STATUS_ENCERRADO = 'ENCERRADO';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['numero', 'serie', 'status', 'volume_total', 'volume_saldo_m3', 'valido_ate', 'nota_fiscal'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $table = 'dofs';

    protected $fillable = [
        'numero',
        'serie',
        'valido_ate',
        'data_emissao',
        'volume_total',
        'volume_saldo_m3',
        'origem',
        'destino',
        'nota_fiscal',
        'status',
        'empresa_id',
    ];

    protected $casts = [
        'valido_ate' => 'datetime',
        'data_emissao' => 'datetime',
        'volume_total' => 'decimal:4',
        'volume_saldo_m3' => 'decimal:4',
    ];

    public function itens(): HasMany
    {
        return $this->hasMany(DofItem::class);
    }

    public function dofLotes(): HasMany
    {
        return $this->hasMany(DofLote::class);
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(Movimentacao::class);
    }

    public function alocacoes(): HasMany
    {
        return $this->hasMany(DofAlocacao::class);
    }

    public function anexosRelacionaveis(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(AnexoRelacionavel::class, 'anexable');
    }

    public function estaAtivo(): bool
    {
        return $this->status === self::STATUS_ATIVO;
    }

    public function getVolumeAlocadoAttribute(): float
    {
        return (float) $this->dofLotes()->sum('volume_m3');
    }

    public function recalcularSaldo(): void
    {
        $saldoItens = (float) $this->itens()->sum('quantidade_disponivel');
        $this->volume_saldo_m3 = max(0, $saldoItens);
        $this->atualizarStatus();
        $this->save();
    }

    public function atualizarStatus(): void
    {
        $saldo = (float) $this->volume_saldo_m3;
        $volumeTotal = (float) $this->volume_total;

        $this->status = match (true) {
            $saldo <= 0 => self::STATUS_ENCERRADO,
            $saldo < $volumeTotal => self::STATUS_PARCIAL,
            default => self::STATUS_ATIVO,
        };
    }

    public function temSaldoDisponivel(float $volume): bool
    {
        return (float) $this->volume_saldo_m3 >= $volume;
    }
}
