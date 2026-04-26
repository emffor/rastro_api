<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Movimentacao extends Model
{
    use HasFactory, HasUuids, BelongsToEmpresa;

    public const TIPO_ENTRADA = 'ENTRADA';
    public const TIPO_TRANSFERENCIA = 'TRANSFERENCIA';
    public const TIPO_BAIXA = 'BAIXA';
    public const TIPO_AJUSTE = 'AJUSTE';

    private const TIPOS_VALIDOS = [
        self::TIPO_ENTRADA,
        self::TIPO_TRANSFERENCIA,
        self::TIPO_BAIXA,
        self::TIPO_AJUSTE,
    ];

    protected $table = 'movimentacoes';

    protected $fillable = [
        'dof_id',
        'dof_item_id',
        'saida_operacao_id',
        'saida_operacao_item_id',
        'lote_origem_id',
        'lote_destino_id',
        'tipo',
        'volume_m3',
        'resumo_produtos',
        'observacao',
        'usuario_id',
        'empresa_id',
    ];

    protected $casts = [
        'volume_m3' => 'decimal:4',
        'resumo_produtos' => 'array',
    ];

    public function dof(): BelongsTo
    {
        return $this->belongsTo(Dof::class);
    }

    public function dofItem(): BelongsTo
    {
        return $this->belongsTo(DofItem::class, 'dof_item_id');
    }

    public function saidaOperacao(): BelongsTo
    {
        return $this->belongsTo(SaidaOperacao::class);
    }

    public function saidaOperacaoItem(): BelongsTo
    {
        return $this->belongsTo(SaidaOperacaoItem::class);
    }

    public function loteOrigem(): BelongsTo
    {
        return $this->belongsTo(Lote::class, 'lote_origem_id');
    }

    public function loteDestino(): BelongsTo
    {
        return $this->belongsTo(Lote::class, 'lote_destino_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public static function tiposValidos(): array
    {
        return self::TIPOS_VALIDOS;
    }

    public function isEntrada(): bool
    {
        return $this->tipo === self::TIPO_ENTRADA;
    }

    public function isTransferencia(): bool
    {
        return $this->tipo === self::TIPO_TRANSFERENCIA;
    }

    public function isBaixa(): bool
    {
        return $this->tipo === self::TIPO_BAIXA;
    }

    public function isAjuste(): bool
    {
        return $this->tipo === self::TIPO_AJUSTE;
    }
}
