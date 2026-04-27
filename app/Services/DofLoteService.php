<?php

namespace App\Services;

use App\Models\Dof;
use App\Models\DofAlocacao;
use App\Models\DofItem;
use App\Models\DofLote;
use App\Models\Lote;
use App\Models\Movimentacao;
use App\Models\ProdutoDimensionado;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DofLoteService
{
    public function __construct(
        private readonly MovimentacaoService $movimentacaoService,
        private readonly AdminMasterContextService $adminMasterContextService,
    ) {}

    public function alocar(string $dofItemId, string $loteId, float $volumeM3, ?string $observacao = null): DofLote
    {
        try {
            return DB::transaction(function () use ($dofItemId, $loteId, $volumeM3, $observacao) {
                $dofItem = DofItem::with('dof')->findOrFail($dofItemId);
                $dof = $dofItem->dof;
                $lote = $this->buscarLoteDaEmpresa($loteId);

                if (!$dof) {
                    throw new \DomainException('DOF não encontrado para o item informado.');
                }

                if ((float) $dofItem->quantidade_disponivel < $volumeM3) {
                    $unidade = $dof->unidade_medida ?? 'm³';
                    throw new \DomainException(
                        "Saldo insuficiente no item do DOF. Disponível: {$dofItem->quantidade_disponivel} {$unidade}, solicitado: {$volumeM3} {$unidade}."
                    );
                }

                $this->validarLoteDestinoDisponivel($lote);

                if ($lote->capacidade_volume && ((float) $lote->volume_ocupado + $volumeM3) > (float) $lote->capacidade_volume) {
                    $unidade = $dof->unidade_medida ?? 'm³';
                    throw new \DomainException(
                        "Capacidade do lote excedida. Disponível: " . ((float) $lote->capacidade_volume - (float) $lote->volume_ocupado) . " {$unidade}."
                    );
                }

                $dofLote = DofLote::create([
                    'dof_id' => $dof->id,
                    'dof_item_id' => $dofItem->id,
                    'lote_id' => $loteId,
                    'volume_m3' => $volumeM3,
                    'observacao' => $observacao,
                ]);

                $dofItem->quantidade_disponivel = (float) $dofItem->quantidade_disponivel - $volumeM3;
                $dofItem->save();

                $dof->recalcularSaldo();
                $lote->recalcularVolumeOcupado();

                $this->movimentacaoService->registrar(
                    dofId: $dof->id,
                    tipo: Movimentacao::TIPO_ENTRADA,
                    volumeM3: $volumeM3,
                    loteDestinoId: $loteId,
                    observacao: $observacao ?? "Alocação do item {$dofItem->id} do DOF {$dof->numero} no lote {$lote->nome}",
                    dofItemId: $dofItem->id,
                );

                $this->criarCabecalhoAlocacao(
                    dofLote: $dofLote,
                    modoAlocacao: DofAlocacao::MODO_MANUAL,
                    volumeTotalM3: $volumeM3,
                    totalPecas: 0,
                    observacao: $observacao,
                );

                return $dofLote->load(['dof', 'dofItem.especie', 'lote.patio', 'alocacao']);
            });
        } catch (\Throwable $e) {
            Log::error('Erro ao alocar DOF em lote', [
                'dof_item_id' => $dofItemId,
                'lote_id' => $loteId,
                'volume_m3' => $volumeM3,
                'erro' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function alocarPorPecas(
        string $dofItemId,
        string $loteId,
        array $linhas,
        ?string $observacao = null,
    ): DofLote {
        try {
            return DB::transaction(function () use ($dofItemId, $loteId, $linhas, $observacao) {
                if (empty($linhas)) {
                    throw new \DomainException('Informe ao menos uma linha para alocação por peças.');
                }

                $dofItem = DofItem::with('dof')->findOrFail($dofItemId);
                $dof = $dofItem->dof;
                $lote = $this->buscarLoteDaEmpresa($loteId);

                if (!$dof) {
                    throw new \DomainException('DOF não encontrado para o item informado.');
                }

                $this->validarLoteDestinoDisponivel($lote);

                $produtoIds = collect($linhas)
                    ->pluck('produto_dimensionado_id')
                    ->filter()
                    ->map(fn ($id) => (string) $id)
                    ->values()
                    ->all();

                $produtos = ProdutoDimensionado::with('especiesVinculadas:id')
                    ->whereIn('id', $produtoIds)
                    ->get()
                    ->keyBy('id');

                if (count($produtoIds) !== $produtos->count()) {
                    throw new \DomainException('Um ou mais produtos dimensionados são inválidos para a empresa atual.');
                }

                $linhasResolvidas = [];
                $totalPecas = 0;
                $volumeTotal = 0.0;

                foreach ($linhas as $index => $linha) {
                    $produtoId = (string) ($linha['produto_dimensionado_id'] ?? '');
                    $quantidadePecas = (int) ($linha['quantidade_pecas'] ?? 0);
                    $produto = $produtos->get($produtoId);

                    if (!$produto) {
                        throw new \DomainException('Produto dimensionado inválido na linha ' . ($index + 1) . '.');
                    }

                    if (!$produto->ativo) {
                        throw new \DomainException("Produto dimensionado '{$produto->nome}' está inativo.");
                    }

                    if ($quantidadePecas <= 0) {
                        throw new \DomainException('Quantidade de peças inválida na linha ' . ($index + 1) . '.');
                    }

                    $this->validarCompatibilidadeProdutoDofItem($dofItem, $produto);

                    $volumeUnitario = (float) $produto->volume_unitario_m3;
                    $volumeLinha = round($volumeUnitario * $quantidadePecas, 4);

                    $totalPecas += $quantidadePecas;
                    $volumeTotal += $volumeLinha;

                    $linhasResolvidas[] = [
                        'ordem' => $index + 1,
                        'produto_dimensionado_id' => $produto->id,
                        'quantidade_pecas' => $quantidadePecas,
                        'volume_unitario_m3' => $volumeUnitario,
                        'volume_total_m3' => $volumeLinha,
                        'produto' => $produto,
                    ];
                }

                $volumeTotal = round($volumeTotal, 4);
                if ($volumeTotal <= 0) {
                    throw new \DomainException('Volume total calculado inválido para alocação por peças.');
                }

                if ((float) $dofItem->quantidade_disponivel < $volumeTotal) {
                    $unidade = $dof->unidade_medida ?? 'm³';
                    throw new \DomainException(
                        "Saldo insuficiente no item do DOF. Disponível: {$dofItem->quantidade_disponivel} {$unidade}, solicitado: {$volumeTotal} {$unidade}."
                    );
                }

                if ($lote->capacidade_volume && ((float) $lote->volume_ocupado + $volumeTotal) > (float) $lote->capacidade_volume) {
                    $unidade = $dof->unidade_medida ?? 'm³';
                    throw new \DomainException(
                        "Capacidade do lote excedida. Disponível: " . ((float) $lote->capacidade_volume - (float) $lote->volume_ocupado) . " {$unidade}."
                    );
                }

                $dofLote = DofLote::create([
                    'dof_id' => $dof->id,
                    'dof_item_id' => $dofItem->id,
                    'lote_id' => $loteId,
                    'volume_m3' => $volumeTotal,
                    'observacao' => $observacao,
                ]);

                $dofItem->quantidade_disponivel = (float) $dofItem->quantidade_disponivel - $volumeTotal;
                $dofItem->save();

                $dof->recalcularSaldo();
                $lote->recalcularVolumeOcupado();

                $this->movimentacaoService->registrar(
                    dofId: $dof->id,
                    tipo: Movimentacao::TIPO_ENTRADA,
                    volumeM3: $volumeTotal,
                    loteDestinoId: $loteId,
                    observacao: $observacao ?? "Alocação por peças do item {$dofItem->id} do DOF {$dof->numero} no lote {$lote->nome}",
                    dofItemId: $dofItem->id,
                    resumoProdutos: $this->mapearResumoProdutosMovimentacao($linhasResolvidas),
                );

                $alocacao = $this->criarCabecalhoAlocacao(
                    dofLote: $dofLote,
                    modoAlocacao: DofAlocacao::MODO_PECAS,
                    volumeTotalM3: $volumeTotal,
                    totalPecas: $totalPecas,
                    observacao: $observacao,
                );

                foreach ($linhasResolvidas as $linha) {
                    /** @var ProdutoDimensionado $produto */
                    $produto = $linha['produto'];
                    $alocacao->linhas()->create([
                        'produto_dimensionado_id' => $produto->id,
                        'ordem' => $linha['ordem'],
                        'quantidade_pecas' => $linha['quantidade_pecas'],
                        'volume_unitario_m3' => $linha['volume_unitario_m3'],
                        'volume_total_m3' => $linha['volume_total_m3'],
                        'produto_nome_snapshot' => $produto->nome,
                        'especie_id_snapshot' => $dofItem->especie_id,
                        'tipo_dof_snapshot' => ProdutoDimensionado::normalizarTipo($produto->tipo_dof),
                        'espessura_cm_snapshot' => $produto->espessura_cm,
                        'largura_cm_snapshot' => $produto->largura_cm,
                        'comprimento_m_snapshot' => $produto->comprimento_m,
                    ]);
                }

                return $dofLote->load(['dof', 'dofItem.especie', 'lote.patio', 'alocacao.linhas']);
            });
        } catch (\Throwable $e) {
            Log::error('Erro ao alocar DOF em lote por peças', [
                'dof_item_id' => $dofItemId,
                'lote_id' => $loteId,
                'linhas' => $linhas,
                'erro' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function transferir(
        string $dofLoteId,
        string $loteDestinoId,
        ?float $volumeM3 = null,
        ?array $linhas = null,
        ?string $observacao = null,
    ): DofLote
    {
        try {
            return DB::transaction(function () use ($dofLoteId, $loteDestinoId, $volumeM3, $linhas, $observacao) {
                $dofLoteOrigem = DofLote::with([
                    'alocacao' => function ($query) {
                        $query->with(['linhas' => function ($linhasQuery) {
                            $linhasQuery->orderBy('ordem');
                        }]);
                    },
                ])->findOrFail($dofLoteId);
                $loteDestino = $this->buscarLoteDaEmpresa($loteDestinoId);
                $alocacaoOrigem = $dofLoteOrigem->alocacao;
                $modoOrigem = $alocacaoOrigem?->modo_alocacao ?? DofAlocacao::MODO_MANUAL;

                if ((string) $dofLoteOrigem->lote_id === (string) $loteDestinoId) {
                    throw new \DomainException('Lote de destino deve ser diferente do lote de origem.');
                }

                $this->validarLoteDestinoDisponivel($loteDestino);
                $loteOrigemId = (string) $dofLoteOrigem->lote_id;
                $dofId = (string) $dofLoteOrigem->dof_id;
                $dofItemId = $dofLoteOrigem->dof_item_id ? (string) $dofLoteOrigem->dof_item_id : null;

                if ($modoOrigem === DofAlocacao::MODO_PECAS) {
                    if ($volumeM3 !== null) {
                        throw new \DomainException('Para alocação por peças, informe apenas linhas de produtos e quantidades.');
                    }
                    if (!$alocacaoOrigem) {
                        throw new \DomainException('Alocação de origem por peças não encontrada.');
                    }

                    $linhasSolicitadas = $this->normalizarLinhasPorProduto(
                        $linhas,
                        'Para transferir alocação por peças, informe linhas com produto e quantidade.',
                    );
                    $resultadoDebito = $this->debitarLinhasDaAlocacaoPorPecas($alocacaoOrigem, $linhasSolicitadas);
                    $volumeTransferido = (float) $resultadoDebito['volume_total_m3'];
                    $totalPecasTransferidas = (int) $resultadoDebito['total_pecas'];
                    $linhasTransferidas = $resultadoDebito['linhas_transferidas'];

                    if ($volumeTransferido <= 0 || $totalPecasTransferidas <= 0) {
                        throw new \DomainException('Não foi possível calcular o volume transferido para as linhas informadas.');
                    }

                    $this->validarCapacidadeLoteDestino($loteDestino, $volumeTransferido, 'destino');

                    $dofLoteDestino = DofLote::create([
                        'dof_id' => $dofId,
                        'dof_item_id' => $dofItemId,
                        'lote_id' => $loteDestinoId,
                        'volume_m3' => $volumeTransferido,
                        'observacao' => $observacao,
                    ]);

                    $alocacaoDestino = $this->criarCabecalhoAlocacao(
                        dofLote: $dofLoteDestino,
                        modoAlocacao: DofAlocacao::MODO_PECAS,
                        volumeTotalM3: $volumeTransferido,
                        totalPecas: $totalPecasTransferidas,
                        observacao: $observacao,
                    );

                    foreach ($linhasTransferidas as $index => $linhaTransferida) {
                        $alocacaoDestino->linhas()->create([
                            'produto_dimensionado_id' => $linhaTransferida['produto_dimensionado_id'],
                            'ordem' => $index + 1,
                            'quantidade_pecas' => $linhaTransferida['quantidade_pecas'],
                            'volume_unitario_m3' => $linhaTransferida['volume_unitario_m3'],
                            'volume_total_m3' => $linhaTransferida['volume_total_m3'],
                            'produto_nome_snapshot' => $linhaTransferida['produto_nome_snapshot'],
                            'especie_id_snapshot' => $linhaTransferida['especie_id_snapshot'],
                            'tipo_dof_snapshot' => $linhaTransferida['tipo_dof_snapshot'],
                            'espessura_cm_snapshot' => $linhaTransferida['espessura_cm_snapshot'],
                            'largura_cm_snapshot' => $linhaTransferida['largura_cm_snapshot'],
                            'comprimento_m_snapshot' => $linhaTransferida['comprimento_m_snapshot'],
                        ]);
                    }

                    if ((int) $alocacaoOrigem->total_pecas <= 0 || (float) $alocacaoOrigem->volume_total_m3 <= 0) {
                        $this->removerDofLoteComAlocacao($dofLoteOrigem, $alocacaoOrigem);
                    } else {
                        $dofLoteOrigem->volume_m3 = (float) $alocacaoOrigem->volume_total_m3;
                        $dofLoteOrigem->save();
                    }

                    $loteOrigem = $this->buscarLoteDaEmpresa($loteOrigemId);
                    $loteOrigem->recalcularVolumeOcupado();
                    $loteDestino->recalcularVolumeOcupado();

                    $this->movimentacaoService->registrar(
                        dofId: $dofId,
                        tipo: Movimentacao::TIPO_TRANSFERENCIA,
                        volumeM3: $volumeTransferido,
                        loteOrigemId: $loteOrigemId,
                        loteDestinoId: $loteDestinoId,
                        observacao: $observacao ?? "Transferência por peças de {$volumeTransferido} " . ($dof->unidade_medida ?? 'm³') . " do lote {$loteOrigem->nome} para {$loteDestino->nome}",
                        dofItemId: $dofItemId,
                        resumoProdutos: $this->mapearResumoProdutosMovimentacao($linhasTransferidas),
                    );

                    return $dofLoteDestino->load([
                        'dof',
                        'dofItem.especie',
                        'lote.patio',
                        'alocacao' => function ($query) {
                            $query->withCount('linhas')->with(['linhas' => function ($linhasQuery) {
                                $linhasQuery->orderBy('ordem');
                            }]);
                        },
                    ]);
                }

                if (!empty($linhas)) {
                    throw new \DomainException('Para alocação manual, informe apenas volume_m3.');
                }
                if ($volumeM3 === null || $volumeM3 <= 0) {
                    throw new \DomainException('Para alocação manual, informe um volume_m3 válido.');
                }
                if ((float) $dofLoteOrigem->volume_m3 < $volumeM3) {
                    $dof = Dof::find($dofLoteOrigem->dof_id);
                    $unidade = $dof->unidade_medida ?? 'm³';
                    throw new \DomainException(
                        "Volume insuficiente na alocação. Disponível: {$dofLoteOrigem->volume_m3} {$unidade}, solicitado: {$volumeM3} {$unidade}."
                    );
                }

                $this->validarCapacidadeLoteDestino($loteDestino, $volumeM3, 'destino');

                $volumeRestante = (float) $dofLoteOrigem->volume_m3 - $volumeM3;

                $dofLoteDestino = DofLote::create([
                    'dof_id' => $dofId,
                    'dof_item_id' => $dofItemId,
                    'lote_id' => $loteDestinoId,
                    'volume_m3' => $volumeM3,
                    'observacao' => $observacao,
                ]);
                $this->criarCabecalhoAlocacao(
                    dofLote: $dofLoteDestino,
                    modoAlocacao: DofAlocacao::MODO_MANUAL,
                    volumeTotalM3: $volumeM3,
                    totalPecas: 0,
                    observacao: $observacao,
                );

                if ($volumeRestante <= 0) {
                    $this->removerDofLoteComAlocacao($dofLoteOrigem, $alocacaoOrigem);
                } else {
                    $dofLoteOrigem->update(['volume_m3' => $volumeRestante]);
                    if ($alocacaoOrigem) {
                        $alocacaoOrigem->update([
                            'volume_total_m3' => round($volumeRestante, 4),
                            'total_pecas' => 0,
                        ]);
                    }
                }

                $loteOrigem = $this->buscarLoteDaEmpresa($loteOrigemId);
                $loteOrigem->recalcularVolumeOcupado();
                $loteDestino->recalcularVolumeOcupado();

                $this->movimentacaoService->registrar(
                    dofId: $dofId,
                    tipo: Movimentacao::TIPO_TRANSFERENCIA,
                    volumeM3: $volumeM3,
                    loteOrigemId: $loteOrigemId,
                    loteDestinoId: $loteDestinoId,
                    observacao: $observacao ?? "Transferência de {$volumeM3} " . ($dof->unidade_medida ?? 'm³') . " do lote {$loteOrigem->nome} para {$loteDestino->nome}",
                    dofItemId: $dofItemId,
                );

                return $dofLoteDestino->load([
                    'dof',
                    'dofItem.especie',
                    'lote.patio',
                    'alocacao' => function ($query) {
                        $query->withCount('linhas')->with(['linhas' => function ($linhasQuery) {
                            $linhasQuery->orderBy('ordem');
                        }]);
                    },
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Erro ao transferir DOF entre lotes', [
                'dof_lote_id' => $dofLoteId,
                'lote_destino_id' => $loteDestinoId,
                'volume_m3' => $volumeM3,
                'linhas' => $linhas,
                'erro' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function baixa(
        string $dofLoteId,
        ?float $volumeM3 = null,
        ?array $linhas = null,
        ?string $observacao = null,
    ): void
    {
        try {
            DB::transaction(function () use ($dofLoteId, $volumeM3, $linhas, $observacao) {
                $dofLote = DofLote::with([
                    'alocacao' => function ($query) {
                        $query->with(['linhas' => function ($linhasQuery) {
                            $linhasQuery->orderBy('ordem');
                        }]);
                    },
                ])->findOrFail($dofLoteId);
                $alocacao = $dofLote->alocacao;
                $modo = $alocacao?->modo_alocacao ?? DofAlocacao::MODO_MANUAL;

                $dofId = (string) $dofLote->dof_id;
                $dofItemId = $dofLote->dof_item_id ? (string) $dofLote->dof_item_id : null;
                $loteId = (string) $dofLote->lote_id;
                $volumeBaixado = 0.0;
                $linhasBaixadas = [];

                if ($modo === DofAlocacao::MODO_PECAS) {
                    if ($volumeM3 !== null) {
                        throw new \DomainException('Para baixa por peças, informe apenas linhas de produtos e quantidades.');
                    }
                    if (!$alocacao) {
                        throw new \DomainException('Alocação por peças não encontrada para a baixa.');
                    }

                    $linhasSolicitadas = $this->normalizarLinhasPorProduto(
                        $linhas,
                        'Para baixa de alocação por peças, informe linhas com produto e quantidade.',
                    );
                    $resultadoDebito = $this->debitarLinhasDaAlocacaoPorPecas($alocacao, $linhasSolicitadas);
                    $volumeBaixado = (float) $resultadoDebito['volume_total_m3'];
                    $linhasBaixadas = $resultadoDebito['linhas_transferidas'];

                    if ((int) $alocacao->total_pecas <= 0 || (float) $alocacao->volume_total_m3 <= 0) {
                        $this->removerDofLoteComAlocacao($dofLote, $alocacao);
                    } else {
                        $dofLote->volume_m3 = (float) $alocacao->volume_total_m3;
                        $dofLote->save();
                    }
                } else {
                    if (!empty($linhas)) {
                        throw new \DomainException('Para baixa manual, informe apenas volume_m3.');
                    }
                    if ($volumeM3 === null || $volumeM3 <= 0) {
                        throw new \DomainException('Para baixa manual, informe um volume_m3 válido.');
                    }
                    if ((float) $dofLote->volume_m3 < $volumeM3) {
                        $dof = Dof::find($dofLote->dof_id);
                        $unidade = $dof->unidade_medida ?? 'm³';
                        throw new \DomainException(
                            "Volume insuficiente para baixa. Disponível: {$dofLote->volume_m3} {$unidade}, solicitado: {$volumeM3} {$unidade}."
                        );
                    }

                    $volumeRestante = (float) $dofLote->volume_m3 - $volumeM3;
                    $volumeBaixado = $volumeM3;

                    if ($volumeRestante <= 0) {
                        $this->removerDofLoteComAlocacao($dofLote, $alocacao);
                    } else {
                        $dofLote->update(['volume_m3' => $volumeRestante]);
                        if ($alocacao) {
                            $alocacao->update([
                                'volume_total_m3' => round($volumeRestante, 4),
                                'total_pecas' => 0,
                            ]);
                        }
                    }
                }

                $dof = Dof::findOrFail($dofId);
                $lote = $this->buscarLoteDaEmpresa($loteId);
                $dofItem = $dofItemId ? DofItem::find($dofItemId) : null;

                if ($dofItem) {
                    $dofItem->quantidade_disponivel = min(
                        (float) $dofItem->quantidade_autorizada,
                        (float) $dofItem->quantidade_disponivel + $volumeBaixado
                    );
                    $dofItem->save();
                }

                $dof->recalcularSaldo();
                $lote->recalcularVolumeOcupado();

                $this->movimentacaoService->registrar(
                    dofId: $dofId,
                    tipo: Movimentacao::TIPO_BAIXA,
                    volumeM3: $volumeBaixado,
                    loteOrigemId: $loteId,
                    observacao: $observacao ?? "Baixa de {$volumeBaixado} " . ($dof->unidade_medida ?? 'm³') . " do lote {$lote->nome}",
                    dofItemId: $dofItemId,
                    resumoProdutos: ($modo === DofAlocacao::MODO_PECAS && !empty($linhasBaixadas))
                        ? $this->mapearResumoProdutosMovimentacao($linhasBaixadas)
                        : null,
                );
            });
        } catch (\Throwable $e) {
            Log::error('Erro ao dar baixa em DOF/Lote', [
                'dof_lote_id' => $dofLoteId,
                'volume_m3' => $volumeM3,
                'linhas' => $linhas,
                'erro' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function removerAlocacao(string $dofLoteId): void
    {
        try {
            DB::transaction(function () use ($dofLoteId) {
                $dofLote = DofLote::with('alocacao')->findOrFail($dofLoteId);

                $dofId = $dofLote->dof_id;
                $dofItemId = $dofLote->dof_item_id;
                $loteId = $dofLote->lote_id;
                $volumeM3 = (float) $dofLote->volume_m3;

                $this->removerDofLoteComAlocacao($dofLote, $dofLote->alocacao);

                $dof = Dof::findOrFail($dofId);
                $dofItem = $dofItemId ? DofItem::find($dofItemId) : null;

                if ($dofItem) {
                    $dofItem->quantidade_disponivel = min(
                        (float) $dofItem->quantidade_autorizada,
                        (float) $dofItem->quantidade_disponivel + $volumeM3
                    );
                    $dofItem->save();
                }

                $dof->recalcularSaldo();

                $lote = $this->buscarLoteDaEmpresa($loteId);
                $lote->recalcularVolumeOcupado();

                $dof = Dof::findOrFail($dofId);
                $unidade = $dof->unidade_medida ?? 'm³';

                $this->movimentacaoService->registrar(
                    dofId: $dofId,
                    tipo: Movimentacao::TIPO_AJUSTE,
                    volumeM3: $volumeM3,
                    loteOrigemId: $loteId,
                    observacao: "Remoção de alocação de {$volumeM3} {$unidade} do lote {$lote->nome}",
                    dofItemId: $dofItemId ? (string) $dofItemId : null,
                );
            });
        } catch (\Throwable $e) {
            Log::error('Erro ao remover alocação DOF/Lote', [
                'dof_lote_id' => $dofLoteId,
                'erro' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function listarPorDof(string $dofId): EloquentCollection
    {
        return DofLote::with([
            'dofItem.especie',
            'lote.patio',
            'alocacao' => function ($query) {
                $query->withCount('linhas')
                    ->with(['linhas' => function ($linhasQuery) {
                        $linhasQuery->with('produtoDimensionado:id,codigo')->orderBy('ordem');
                    }]);
            },
        ])
            ->where('dof_id', $dofId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function listarPorLote(string $loteId): EloquentCollection
    {
        $lote = $this->buscarLoteDaEmpresa($loteId);

        return DofLote::with([
            'dof',
            'dofItem.especie',
            'alocacao' => function ($query) {
                $query->withCount('linhas')
                    ->with(['linhas' => function ($linhasQuery) {
                        $linhasQuery->with('produtoDimensionado:id,codigo')->orderBy('ordem');
                    }]);
            },
        ])
            ->where('lote_id', $lote->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function montarResumoPecasDoDofLote(DofLote $dofLote): array
    {
        $alocacao = $dofLote->alocacao;
        if (!$alocacao || $alocacao->modo_alocacao !== DofAlocacao::MODO_PECAS) {
            return [
                'total_pecas' => 0,
                'total_volume_m3' => round((float) $dofLote->volume_m3, 4),
                'produtos' => [],
            ];
        }

        if (!$alocacao->relationLoaded('linhas')) {
            $alocacao->load(['linhas' => function ($query) {
                $query->with('produtoDimensionado:id,codigo')->orderBy('ordem');
            }]);
        }

        $produtosMap = [];
        $totalPecas = 0;
        $totalVolume = 0.0;

        foreach ($alocacao->linhas as $linha) {
            $produtoId = $linha->produto_dimensionado_id ? (string) $linha->produto_dimensionado_id : 'SEM_PRODUTO';
            if (!isset($produtosMap[$produtoId])) {
                $produtosMap[$produtoId] = [
                    'produto_dimensionado_id' => $linha->produto_dimensionado_id,
                    'produto_codigo' => $linha->produtoDimensionado?->codigo,
                    'produto_nome' => (string) $linha->produto_nome_snapshot,
                    'quantidade_pecas' => 0,
                    'volume_unitario_m3' => (float) $linha->volume_unitario_m3,
                    'volume_total_m3' => 0.0,
                ];
            }

            $quantidadeLinha = (int) $linha->quantidade_pecas;
            $volumeLinha = (float) $linha->volume_total_m3;

            $produtosMap[$produtoId]['quantidade_pecas'] += $quantidadeLinha;
            $produtosMap[$produtoId]['volume_total_m3'] += $volumeLinha;
            $totalPecas += $quantidadeLinha;
            $totalVolume += $volumeLinha;
        }

        $produtos = array_values(array_map(function (array $produto) {
            $produto['volume_total_m3'] = round((float) $produto['volume_total_m3'], 4);
            return $produto;
        }, $produtosMap));

        usort($produtos, fn (array $a, array $b) => strcmp((string) $a['produto_nome'], (string) $b['produto_nome']));

        return [
            'total_pecas' => $totalPecas,
            'total_volume_m3' => round($totalVolume, 4),
            'produtos' => $produtos,
        ];
    }

    public function montarResumoConsolidado(EloquentCollection $dofLotes): array
    {
        $totalPecas = 0;
        $totalVolume = 0.0;
        $itensMap = [];
        $produtosMap = [];

        foreach ($dofLotes as $dofLote) {
            $resumoPecas = $this->montarResumoPecasDoDofLote($dofLote);
            $totalPecas += (int) $resumoPecas['total_pecas'];
            $totalVolume += (float) $resumoPecas['total_volume_m3'];

            if ($dofLote->dof_item_id) {
                $itemKey = (string) $dofLote->dof_item_id;
                if (!isset($itensMap[$itemKey])) {
                    $itensMap[$itemKey] = [
                        'dof_item_id' => $itemKey,
                        'especie_id' => $dofLote->dofItem?->especie_id,
                        'especie_nome' => $this->formatarNomeEspecieDofItem($dofLote->dofItem),
                        'total_pecas' => 0,
                        'volume_total_m3' => 0.0,
                    ];
                }
                $itensMap[$itemKey]['total_pecas'] += (int) $resumoPecas['total_pecas'];
                $itensMap[$itemKey]['volume_total_m3'] += (float) $resumoPecas['total_volume_m3'];
            }

            foreach ($resumoPecas['produtos'] as $produtoResumo) {
                $produtoKey = $produtoResumo['produto_dimensionado_id']
                    ? (string) $produtoResumo['produto_dimensionado_id']
                    : 'SEM_PRODUTO::' . (string) $produtoResumo['produto_nome'];
                if (!isset($produtosMap[$produtoKey])) {
                    $produtosMap[$produtoKey] = [
                        'produto_dimensionado_id' => $produtoResumo['produto_dimensionado_id'],
                        'produto_codigo' => $produtoResumo['produto_codigo'] ?? null,
                        'produto_nome' => (string) $produtoResumo['produto_nome'],
                        'total_pecas' => 0,
                        'volume_total_m3' => 0.0,
                    ];
                }
                $produtosMap[$produtoKey]['total_pecas'] += (int) $produtoResumo['quantidade_pecas'];
                $produtosMap[$produtoKey]['volume_total_m3'] += (float) $produtoResumo['volume_total_m3'];
            }
        }

        $itens = array_values(array_map(function (array $item) {
            $item['volume_total_m3'] = round((float) $item['volume_total_m3'], 4);
            return $item;
        }, $itensMap));
        usort($itens, fn (array $a, array $b) => strcmp((string) $a['especie_nome'], (string) $b['especie_nome']));

        $produtos = array_values(array_map(function (array $produto) {
            $produto['volume_total_m3'] = round((float) $produto['volume_total_m3'], 4);
            return $produto;
        }, $produtosMap));
        usort($produtos, fn (array $a, array $b) => strcmp((string) $a['produto_nome'], (string) $b['produto_nome']));

        return [
            'total_pecas' => $totalPecas,
            'total_volume_m3' => round($totalVolume, 4),
            'itens_dof' => $itens,
            'produtos_dimensionados' => $produtos,
        ];
    }

    public function detalharAlocacaoPorDofLote(string $dofLoteId): DofAlocacao
    {
        $alocacao = DofAlocacao::with([
            'dof',
            'dofItem.especie',
            'lote.patio',
            'linhas' => function ($query) {
                $query->orderBy('ordem');
            },
            'linhas.produtoDimensionado.especie',
            'usuario',
        ])->where('dof_lote_id', $dofLoteId)->first();

        if (!$alocacao) {
            throw new \DomainException('Detalhe de alocação não encontrado para este dof_lote.');
        }

        return $alocacao;
    }

    private function validarLoteDestinoDisponivel(Lote $lote): void
    {
        if ($lote->status === 'BLOQUEADO') {
            throw new \DomainException("Lote '{$lote->nome}' está bloqueado e não pode receber alocação.");
        }
    }

    private function buscarLoteDaEmpresa(string $loteId): Lote
    {
        $empresaId = request()->get('empresa_id') ?: auth()->user()?->empresa_id;
        $query = Lote::query()->with('patio:id,empresa_id,nome');

        if ($empresaId) {
            $query->whereHas('patio', function ($patioQuery) use ($empresaId) {
                $patioQuery->where('empresa_id', $empresaId);
            });
        }

        $lote = $query->findOrFail($loteId);

        if ($empresaId && (string) $lote->patio?->empresa_id !== (string) $empresaId) {
            throw new \DomainException('Lote inválido para a empresa atual.');
        }

        return $lote;
    }

    /**
     * @param array<int, array{
     *   produto_dimensionado_id?: string,
     *   quantidade_pecas: int,
     *   volume_unitario_m3: float,
     *   volume_total_m3: float,
     *   produto_nome_snapshot?: string,
     *   produto?: \App\Models\ProdutoDimensionado
     * }> $linhas
     * @return array<int, array{
     *   produto_dimensionado_id: string|null,
     *   produto_codigo?: string|null,
     *   produto_nome: string,
     *   quantidade_pecas: int,
     *   volume_unitario_m3: float,
     *   volume_total_m3: float
     * }>
     */
    private function mapearResumoProdutosMovimentacao(array $linhas): array
    {
        $resumo = [];

        foreach ($linhas as $linha) {
            $produtoId = isset($linha['produto_dimensionado_id']) ? (string) $linha['produto_dimensionado_id'] : null;
            $nomeSnapshot = (string) ($linha['produto_nome_snapshot'] ?? '');
            $nomeProduto = trim($nomeSnapshot);

            if ($nomeProduto === '' && isset($linha['produto']) && $linha['produto'] instanceof ProdutoDimensionado) {
                $nomeProduto = (string) $linha['produto']->nome;
            }
            if ($nomeProduto === '') {
                $nomeProduto = 'Produto';
            }

            $quantidade = (int) ($linha['quantidade_pecas'] ?? 0);
            $volumeUnitario = round((float) ($linha['volume_unitario_m3'] ?? 0), 6);
            $volumeTotal = round((float) ($linha['volume_total_m3'] ?? 0), 4);

            $chave = ($produtoId ?: 'SEM_PRODUTO') . '::' . $nomeProduto;
            if (!isset($resumo[$chave])) {
                $resumo[$chave] = [
                    'produto_dimensionado_id' => $produtoId,
                    'produto_codigo' => isset($linha['produto']) && $linha['produto'] instanceof ProdutoDimensionado
                        ? $linha['produto']->codigo
                        : null,
                    'produto_nome' => $nomeProduto,
                    'quantidade_pecas' => 0,
                    'volume_unitario_m3' => $volumeUnitario,
                    'volume_total_m3' => 0.0,
                ];
            }

            $resumo[$chave]['quantidade_pecas'] += $quantidade;
            $resumo[$chave]['volume_total_m3'] = round(
                (float) $resumo[$chave]['volume_total_m3'] + $volumeTotal,
                4,
            );
        }

        return array_values($resumo);
    }

    /**
     * @param array<int, array{produto_dimensionado_id?: string, quantidade_pecas?: int}>|null $linhas
     * @return array<string, int>
     */
    private function normalizarLinhasPorProduto(?array $linhas, string $mensagemSemLinhas): array
    {
        if (empty($linhas)) {
            throw new \DomainException($mensagemSemLinhas);
        }

        $normalizadas = [];
        foreach ($linhas as $index => $linha) {
            $produtoId = (string) ($linha['produto_dimensionado_id'] ?? '');
            $quantidade = (int) ($linha['quantidade_pecas'] ?? 0);

            if ($produtoId === '') {
                throw new \DomainException('Produto dimensionado inválido na linha ' . ($index + 1) . '.');
            }
            if ($quantidade <= 0) {
                throw new \DomainException('Quantidade de peças inválida na linha ' . ($index + 1) . '.');
            }

            $normalizadas[$produtoId] = ($normalizadas[$produtoId] ?? 0) + $quantidade;
        }

        return $normalizadas;
    }

    private function validarCapacidadeLoteDestino(Lote $loteDestino, float $volumeM3, string $escopoMensagem): void
    {
        if ($loteDestino->capacidade_volume && ((float) $loteDestino->volume_ocupado + $volumeM3) > (float) $loteDestino->capacidade_volume) {
            throw new \DomainException(
                "Capacidade do lote {$escopoMensagem} excedida. Disponível: " . ((float) $loteDestino->capacidade_volume - (float) $loteDestino->volume_ocupado) . "."
            );
        }
    }

    /**
     * @param array<string, int> $linhasSolicitadas
     * @return array{
     *   total_pecas: int,
     *   volume_total_m3: float,
     *   linhas_transferidas: array<int, array{
     *     produto_dimensionado_id: string,
     *     quantidade_pecas: int,
     *     volume_unitario_m3: float,
     *     volume_total_m3: float,
     *     produto_nome_snapshot: string,
     *     especie_id_snapshot: string,
     *     tipo_dof_snapshot: string,
     *     espessura_cm_snapshot: float,
     *     largura_cm_snapshot: float,
     *     comprimento_m_snapshot: float
     *   }>
     * }
     */
    private function debitarLinhasDaAlocacaoPorPecas(DofAlocacao $alocacao, array $linhasSolicitadas): array
    {
        if (!$alocacao->relationLoaded('linhas')) {
            $alocacao->load(['linhas' => function ($query) {
                $query->orderBy('ordem');
            }]);
        }

        $linhasAtuais = $alocacao->linhas instanceof Collection
            ? $alocacao->linhas
            : collect($alocacao->linhas);
        $grupos = $linhasAtuais
            ->filter(fn ($linha) => !empty($linha->produto_dimensionado_id))
            ->groupBy(fn ($linha) => (string) $linha->produto_dimensionado_id);

        $linhasTransferidas = [];
        $totalPecas = 0;
        $volumeTotal = 0.0;

        foreach ($linhasSolicitadas as $produtoId => $quantidadeSolicitada) {
            /** @var Collection<int, \App\Models\DofAlocacaoLinha>|null $linhasProduto */
            $linhasProduto = $grupos->get($produtoId);
            if (!$linhasProduto || $linhasProduto->isEmpty()) {
                throw new \DomainException('Produto selecionado não encontrado na alocação de origem.');
            }

            $quantidadeDisponivel = (int) $linhasProduto->sum('quantidade_pecas');
            if ($quantidadeSolicitada > $quantidadeDisponivel) {
                throw new \DomainException("Quantidade solicitada acima do disponível para o produto selecionado. Disponível: {$quantidadeDisponivel}.");
            }

            $linhaReferencia = $linhasProduto->first();
            if (!$linhaReferencia) {
                continue;
            }
            $volumeUnitario = (float) $linhaReferencia->volume_unitario_m3;
            $volumeLinhaTransferida = round($volumeUnitario * $quantidadeSolicitada, 4);

            $linhasTransferidas[] = [
                'produto_dimensionado_id' => (string) $produtoId,
                'quantidade_pecas' => $quantidadeSolicitada,
                'volume_unitario_m3' => $volumeUnitario,
                'volume_total_m3' => $volumeLinhaTransferida,
                'produto_nome_snapshot' => (string) $linhaReferencia->produto_nome_snapshot,
                'especie_id_snapshot' => (string) $linhaReferencia->especie_id_snapshot,
                'tipo_dof_snapshot' => (string) $linhaReferencia->tipo_dof_snapshot,
                'espessura_cm_snapshot' => (float) $linhaReferencia->espessura_cm_snapshot,
                'largura_cm_snapshot' => (float) $linhaReferencia->largura_cm_snapshot,
                'comprimento_m_snapshot' => (float) $linhaReferencia->comprimento_m_snapshot,
            ];

            $quantidadeRestanteDebitar = $quantidadeSolicitada;
            foreach ($linhasProduto as $linhaOrigem) {
                if ($quantidadeRestanteDebitar <= 0) {
                    break;
                }

                $quantidadeLinha = (int) $linhaOrigem->quantidade_pecas;
                $consumida = min($quantidadeLinha, $quantidadeRestanteDebitar);
                $novaQuantidade = $quantidadeLinha - $consumida;

                if ($novaQuantidade <= 0) {
                    $linhaOrigem->delete();
                } else {
                    $linhaOrigem->update([
                        'quantidade_pecas' => $novaQuantidade,
                        'volume_total_m3' => round(((float) $linhaOrigem->volume_unitario_m3) * $novaQuantidade, 4),
                    ]);
                }

                $quantidadeRestanteDebitar -= $consumida;
            }

            $totalPecas += $quantidadeSolicitada;
            $volumeTotal += $volumeLinhaTransferida;
        }

        $this->reordenarLinhasAlocacao($alocacao);
        $this->recalcularCabecalhoPorLinhas($alocacao);

        return [
            'total_pecas' => $totalPecas,
            'volume_total_m3' => round($volumeTotal, 4),
            'linhas_transferidas' => $linhasTransferidas,
        ];
    }

    private function reordenarLinhasAlocacao(DofAlocacao $alocacao): void
    {
        $linhas = $alocacao->linhas()->orderBy('ordem')->orderBy('created_at')->get();
        foreach ($linhas as $index => $linha) {
            $novaOrdem = $index + 1;
            if ((int) $linha->ordem !== $novaOrdem) {
                $linha->update(['ordem' => $novaOrdem]);
            }
        }
    }

    private function recalcularCabecalhoPorLinhas(DofAlocacao $alocacao): void
    {
        $totalPecas = (int) $alocacao->linhas()->sum('quantidade_pecas');
        $volumeTotal = round((float) $alocacao->linhas()->sum('volume_total_m3'), 4);

        $alocacao->update([
            'total_pecas' => $totalPecas,
            'volume_total_m3' => $volumeTotal,
        ]);
    }

    private function removerDofLoteComAlocacao(DofLote $dofLote, ?DofAlocacao $alocacao = null): void
    {
        $alocacaoPersistida = $alocacao ?? DofAlocacao::where('dof_lote_id', $dofLote->id)->first();
        if ($alocacaoPersistida) {
            $alocacaoPersistida->delete();
        }
        $dofLote->delete();
    }

    private function formatarNomeEspecieDofItem(?DofItem $dofItem): string
    {
        $especie = $dofItem?->especie;
        if (!$especie) {
            return 'Sem espécie';
        }

        $tipo = trim((string) ($especie->tipoSerragem?->nome ?? $especie->nome_tipo ?? $especie->tipo ?? ''));
        $cientifico = trim((string) ($especie->nome_cientifico ?? ''));
        $popular = trim((string) ($especie->nome_popular ?? ''));

        if ($tipo !== '' && $cientifico !== '' && $popular !== '') {
            return "{$tipo} / {$cientifico} - {$popular}";
        }

        return $especie->nome_formatado
            ?? ($popular !== '' ? $popular : ($cientifico !== '' ? $cientifico : 'Sem espécie'));
    }

    private function validarCompatibilidadeProdutoDofItem(DofItem $dofItem, ProdutoDimensionado $produto): void
    {
        $especiesVinculadas = $produto->especiesVinculadas
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        if (empty($especiesVinculadas)) {
            $especiesVinculadas = [(string) $produto->especie_id];
        }

        if (!in_array((string) $dofItem->especie_id, $especiesVinculadas, true)) {
            throw new \DomainException("Produto dimensionado '{$produto->nome}' incompatível com a espécie do item DOF selecionado (grupo de espécies vinculadas).");
        }

        $tipoProduto = ProdutoDimensionado::normalizarTipo($produto->tipo_dof);
        $tipoDofItem = ProdutoDimensionado::normalizarTipo($dofItem->tipo);

        if ($tipoDofItem !== '' && $tipoProduto !== '' && $tipoProduto !== $tipoDofItem) {
            throw new \DomainException("Produto dimensionado '{$produto->nome}' incompatível com o tipo do item DOF selecionado.");
        }
    }

    private function criarCabecalhoAlocacao(
        DofLote $dofLote,
        string $modoAlocacao,
        float $volumeTotalM3,
        int $totalPecas,
        ?string $observacao,
    ): DofAlocacao {
        return DofAlocacao::create([
            'empresa_id' => $dofLote->empresa_id,
            'dof_id' => $dofLote->dof_id,
            'dof_item_id' => $dofLote->dof_item_id,
            'lote_id' => $dofLote->lote_id,
            'dof_lote_id' => $dofLote->id,
            'modo_alocacao' => $modoAlocacao,
            'volume_total_m3' => round($volumeTotalM3, 4),
            'total_pecas' => max(0, $totalPecas),
            'observacao' => $observacao,
            'usuario_id' => $this->adminMasterContextService->usuarioEfetivoId(),
        ]);
    }
}
