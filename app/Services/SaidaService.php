<?php

namespace App\Services;

use App\Models\DofAlocacao;
use App\Models\DofAlocacaoLinha;
use App\Models\DofLote;
use App\Models\Especie;
use App\Models\Lote;
use App\Models\Movimentacao;
use App\Models\SaidaConsumo;
use App\Models\SaidaConsumoProduto;
use App\Models\SaidaOperacao;
use App\Models\SaidaOperacaoItem;
use App\Support\ProdutoDimensionadoEspecieMatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SaidaService
{
    private const TOLERANCIA_VOLUME = 0.0001;

    public function __construct(
        private readonly MovimentacaoService $movimentacaoService,
        private readonly AdminMasterContextService $adminMasterContextService,
    ) {}

    public function previewSaldoEspecie(string $especieId): array
    {
        $especie = Especie::findOrFail($especieId);

        $volumeDisponivel = (float) DofLote::query()
            ->join('dof_itens', 'dof_itens.id', '=', 'dof_lotes.dof_item_id')
            ->where('dof_itens.especie_id', $especieId)
            ->where('dof_lotes.volume_m3', '>', 0)
            ->sum('dof_lotes.volume_m3');

        return [
            'especie_id' => $especie->id,
            'especie_nome' => $especie->nome_formatado ?: ($especie->nome_popular ?: $especie->nome_cientifico),
            'volume_disponivel_m3' => round($volumeDisponivel, 4),
        ];
    }

    public function listarEspeciesComSaldoDisponivel(): array
    {
        $saldosPorEspecie = DB::table('dof_lotes')
            ->join('dof_itens', 'dof_itens.id', '=', 'dof_lotes.dof_item_id')
            ->select('dof_itens.especie_id', DB::raw('SUM(dof_lotes.volume_m3) as volume_disponivel_m3'))
            ->where('dof_lotes.volume_m3', '>', 0)
            ->groupBy('dof_itens.especie_id');

        return Especie::query()
            ->with('tipoSerragem')
            ->leftJoinSub($saldosPorEspecie, 'saldos', function ($join) {
                $join->on('saldos.especie_id', '=', 'especies.id');
            })
            ->whereRaw('COALESCE(saldos.volume_disponivel_m3, 0) > 0')
            ->orderBy('especies.nome_formatado')
            ->orderBy('especies.nome_popular')
            ->get([
                'especies.id',
                'especies.tipo_serragem_id',
                'especies.nome_cientifico',
                'especies.nome_popular',
                'especies.tipo',
                'especies.nome_tipo',
                'especies.nome_formatado',
                'especies.empresa_id',
                'especies.created_at',
                'especies.updated_at',
                'especies.deleted_at',
                DB::raw('COALESCE(saldos.volume_disponivel_m3, 0) as volume_disponivel_m3'),
            ])
            ->map(function (Especie $especie): array {
                return [
                    'id' => $especie->id,
                    'nome_cientifico' => $especie->nome_cientifico,
                    'nome_popular' => $especie->nome_popular,
                    'tipo_serragem_id' => $especie->tipo_serragem_id,
                    'tipo_serragem' => $especie->tipoSerragem,
                    'tipo' => $especie->resolverTipoSerragemNome(),
                    'nome_tipo' => ProdutoDimensionadoEspecieMatcher::normalizarNomeTipoDescricao(
                        $especie->nome_tipo,
                        $especie->resolverTipoSerragemNome(),
                    ),
                    'nome_formatado' => $especie->nome_formatado,
                    'empresa_id' => $especie->empresa_id,
                    'created_at' => $especie->created_at,
                    'updated_at' => $especie->updated_at,
                    'deleted_at' => $especie->deleted_at,
                    'volume_disponivel_m3' => round((float) $especie->volume_disponivel_m3, 4),
                ];
            })
            ->values()
            ->all();
    }

    public function previewProdutosEspecie(string $especieId): array
    {
        $especie = Especie::findOrFail($especieId);
        $fontes = $this->carregarFontesEspecie($especieId, false);

        $produtosMap = [];

        foreach ($fontes as $fonte) {
            $produtosFonte = $this->montarProdutosDisponiveisDaFonte($fonte, $especie);

            foreach ($produtosFonte as $produto) {
                $produtoId = (string) ($produto['produto_dimensionado_id'] ?? '');
                if ($produtoId === '') {
                    continue;
                }

                if (!isset($produtosMap[$produtoId])) {
                    $produtosMap[$produtoId] = [
                        'produto_dimensionado_id' => $produtoId,
                        'produto_nome' => (string) $produto['produto_nome'],
                        'quantidade_disponivel' => 0,
                        'volume_unitario_m3' => round((float) $produto['volume_unitario_m3'], 6),
                        'volume_disponivel_m3' => 0.0,
                    ];
                }

                $produtosMap[$produtoId]['quantidade_disponivel'] += (int) $produto['quantidade_disponivel'];
                $produtosMap[$produtoId]['volume_disponivel_m3'] += (float) $produto['volume_disponivel_m3'];
            }
        }

        $produtos = array_values($produtosMap);
        usort($produtos, fn (array $a, array $b) => strcmp((string) $a['produto_nome'], (string) $b['produto_nome']));

        $produtos = array_map(function (array $produto): array {
            $produto['volume_disponivel_m3'] = round((float) $produto['volume_disponivel_m3'], 4);
            return $produto;
        }, $produtos);

        return [
            'especie_id' => $especie->id,
            'especie_nome' => $especie->nome_formatado ?: ($especie->nome_popular ?: $especie->nome_cientifico),
            'produtos' => $produtos,
        ];
    }

    public function previewSaidaDimensionados(array $dados): array
    {
        $estadoPorEspecie = [];
        $itensPreview = [];

        foreach ($dados['itens'] as $index => $itemPayload) {
            $especieId = (string) $itemPayload['especie_id'];
            $volumeSolicitado = (float) $itemPayload['volume_m3'];
            $itemRef = (string) ($itemPayload['item_ref'] ?? ('item-' . ($index + 1)));
            $fontesPreferidas = $this->normalizarFontesPreferidas($itemPayload['fontes_preferidas'] ?? null);
            $fontesConsumo = $this->normalizarFontesConsumo($itemPayload['fontes_consumo'] ?? null);

            if (!isset($estadoPorEspecie[$especieId])) {
                $especieObj = Especie::find($especieId);
                $estadoPorEspecie[$especieId] = $this->criarEstadoEspecie($this->carregarFontesEspecie($especieId, false), $especieObj);
            }

            $estadoBase = $estadoPorEspecie[$especieId];
            $estadoAtualComPrioridade = $this->reordenarEstadoPorPreferidas($estadoBase, $fontesPreferidas);
            $plano = $this->montarPlanoConsumoDoEstado(
                especieId: $especieId,
                volumeSolicitado: $volumeSolicitado,
                fontesEstado: $estadoAtualComPrioridade,
                incluirFontesSemConsumo: !empty($fontesPreferidas),
                consumosPreferenciais: $fontesConsumo,
            );

            $itensPreview[] = [
                'item_ref' => $itemRef,
                'especie_id' => $especieId,
                'volume_solicitado_m3' => round($volumeSolicitado, 4),
                'volume_disponivel_m3' => round((float) $plano['volume_disponivel_m3'], 4),
                'volume_origem_pecas_m3' => round((float) $plano['volume_origem_pecas_m3'], 4),
                'volume_origem_manual_m3' => round((float) $plano['volume_origem_manual_m3'], 4),
                'ajuste_necessario' => (bool) $plano['ajuste_necessario'],
                'plano_token' => $plano['plano_token'],
                'fontes_disponiveis' => $this->montarFontesDisponiveis($estadoBase),
                'fontes' => $plano['fontes'],
            ];

            $estadoPorEspecie[$especieId] = $this->mesclarEstadoAtualizado(
                estadoBase: $estadoBase,
                estadoAtualizado: $plano['estado_atualizado'],
            );
        }

        return ['itens' => $itensPreview];
    }

    public function registrarSaidaGlobal(array $dados): SaidaOperacao
    {
        return DB::transaction(function () use ($dados) {
            /** @var SaidaOperacao $operacao */
            $operacao = SaidaOperacao::create([
                'usuario_id' => $this->adminMasterContextService->usuarioEfetivoId(),
                'observacao' => $dados['observacao_geral'] ?? null,
            ]);

            $lotesRecalcular = [];

            foreach ($dados['itens'] as $itemPayload) {
                $especieId = (string) $itemPayload['especie_id'];
                $volumeSolicitado = (float) $itemPayload['volume_m3'];
                $fontesPreferidas = $this->normalizarFontesPreferidas($itemPayload['fontes_preferidas'] ?? null);
                $fontesConsumo = $this->normalizarFontesConsumo($itemPayload['fontes_consumo'] ?? null);

                $fontes = $this->carregarFontesEspecie($especieId, true, $fontesPreferidas);
                $plano = $this->montarPlanoConsumoEmFontes(
                    especieId: $especieId,
                    volumeSolicitado: $volumeSolicitado,
                    fontes: $fontes,
                    incluirFontesSemConsumo: !empty($fontesPreferidas),
                    consumosPreferenciais: $fontesConsumo,
                );

                /** @var SaidaOperacaoItem $saidaItem */
                $saidaItem = $operacao->itens()->create([
                    'especie_id' => $especieId,
                    'volume_solicitado_m3' => $volumeSolicitado,
                    'volume_baixado_m3' => 0,
                    'volume_sem_produto_m3' => 0,
                    'observacao' => $itemPayload['observacao'] ?? null,
                ]);

                foreach ($itemPayload['notas_fiscais'] as $notaFiscal) {
                    $saidaItem->notasFiscais()->create([
                        'numero_nf' => $notaFiscal['numero_nf'],
                        'data_emissao_nf' => $notaFiscal['data_emissao_nf'],
                    ]);
                }

                $baixaProdutosPorFonte = $this->validarBaixaProdutosDoItem($itemPayload, $plano);

                $fontesPorId = $fontes->keyBy(fn (DofLote $fonte) => (string) $fonte->id);
                $volumeBaixado = 0.0;
                $volumeSemProduto = 0.0;

                foreach ($plano['fontes'] as $fontePlano) {
                    $dofLoteId = (string) $fontePlano['dof_lote_id'];
                    $consumo = (float) $fontePlano['volume_consumo_m3'];

                    /** @var DofLote|null $fonte */
                    $fonte = $fontesPorId->get($dofLoteId);
                    if (!$fonte) {
                        throw new \DomainException('Fonte de consumo não encontrada para registro da saída.');
                    }

                    $modoAlocacao = (string) $fontePlano['modo_alocacao'];
                    $consumoProdutosRegistros = [];

                    if ($modoAlocacao === DofAlocacao::MODO_PECAS) {
                        $linhasSolicitadas = $baixaProdutosPorFonte[$dofLoteId] ?? [];
                        if (empty($linhasSolicitadas)) {
                            continue;
                        }

                        $resultadoDebito = $this->debitarLinhasAlocacaoPorPecas($fonte, $linhasSolicitadas);
                        $volumeLinhas = (float) $resultadoDebito['volume_total_m3'];
                        $consumo = $volumeLinhas;
                        $consumoProdutosRegistros = $resultadoDebito['produtos'];
                    } else {
                        $volumeSemProduto += $consumo;
                    }

                    if ($consumo <= self::TOLERANCIA_VOLUME) {
                        continue;
                    }

                    $volumeAntes = (float) $fonte->volume_m3;
                    $novoVolume = round($volumeAntes - $consumo, 4);
                    $dofLoteIdHistorico = (string) $fonte->id;

                    if ($modoAlocacao === DofAlocacao::MODO_PECAS && $fonte->relationLoaded('alocacao') && $fonte->alocacao) {
                        $fonte->alocacao->refresh();
                        $novoVolume = round((float) $fonte->alocacao->volume_total_m3, 4);
                    }

                    if ($novoVolume <= self::TOLERANCIA_VOLUME) {
                        $dofLoteIdHistorico = null;
                        $this->removerDofLoteComAlocacao($fonte);
                    } else {
                        $fonte->update(['volume_m3' => $novoVolume]);
                        if ($modoAlocacao !== DofAlocacao::MODO_PECAS && $fonte->relationLoaded('alocacao') && $fonte->alocacao) {
                            $fonte->alocacao->update([
                                'volume_total_m3' => $novoVolume,
                                'total_pecas' => 0,
                            ]);
                        }
                    }

                    $saidaConsumo = SaidaConsumo::create([
                        'saida_operacao_item_id' => $saidaItem->id,
                        'dof_id' => $fonte->dof_id,
                        'dof_item_id' => $fonte->dof_item_id,
                        'dof_lote_id' => $dofLoteIdHistorico,
                        'lote_id' => $fonte->lote_id,
                        'volume_m3' => $consumo,
                    ]);

                    foreach ($consumoProdutosRegistros as $produtoRegistro) {
                        SaidaConsumoProduto::create([
                            'saida_consumo_id' => $saidaConsumo->id,
                            'saida_operacao_item_id' => $saidaItem->id,
                            'produto_dimensionado_id' => $produtoRegistro['produto_dimensionado_id'],
                            'quantidade_pecas' => $produtoRegistro['quantidade_pecas'],
                            'volume_unitario_m3' => $produtoRegistro['volume_unitario_m3'],
                            'volume_total_m3' => $produtoRegistro['volume_total_m3'],
                            'produto_nome_snapshot' => $produtoRegistro['produto_nome_snapshot'],
                        ]);
                    }

                    $this->movimentacaoService->registrar(
                        dofId: $fonte->dof_id,
                        tipo: Movimentacao::TIPO_BAIXA,
                        volumeM3: $consumo,
                        loteOrigemId: $fonte->lote_id,
                        observacao: $itemPayload['observacao'] ?? "Saída fiscal por espécie {$especieId}",
                        dofItemId: $fonte->dof_item_id,
                        resumoProdutos: !empty($consumoProdutosRegistros)
                            ? array_map(static fn (array $registro): array => [
                                'produto_dimensionado_id' => $registro['produto_dimensionado_id'],
                                'produto_nome' => $registro['produto_nome_snapshot'],
                                'quantidade_pecas' => (int) $registro['quantidade_pecas'],
                                'volume_unitario_m3' => round((float) $registro['volume_unitario_m3'], 6),
                                'volume_total_m3' => round((float) $registro['volume_total_m3'], 4),
                            ], $consumoProdutosRegistros)
                            : null,
                        saidaOperacaoId: $operacao->id,
                        saidaOperacaoItemId: $saidaItem->id,
                    );

                    $lotesRecalcular[$fonte->lote_id] = true;
                    $volumeBaixado += $consumo;
                }

                $saidaItem->update([
                    'volume_baixado_m3' => round($volumeBaixado, 4),
                    'volume_sem_produto_m3' => round($volumeSemProduto, 4),
                ]);
            }

            foreach (array_keys($lotesRecalcular) as $loteId) {
                $lote = Lote::find($loteId);
                if ($lote) {
                    $lote->recalcularVolumeOcupado();
                }
            }

            return $operacao->load([
                'usuario',
                'itens.especie',
                'itens.notasFiscais',
                'itens.consumos.dof',
                'itens.consumos.dofItem.especie',
                'itens.consumos.lote.patio',
                'itens.consumos.consumoProdutos',
                'itens.consumoProdutos',
            ]);
        });
    }

    public function buscarSaidaOperacao(string $id): SaidaOperacao
    {
        return SaidaOperacao::with([
            'usuario',
            'itens.especie',
            'itens.notasFiscais',
            'itens.consumos.dof',
            'itens.consumos.dofItem.especie',
            'itens.consumos.lote.patio',
            'itens.consumos.consumoProdutos',
            'itens.consumoProdutos',
        ])->findOrFail($id);
    }

    /**
     * @param Collection<int, DofLote> $fontes
     */
    private function montarPlanoConsumoEmFontes(
        string $especieId,
        float $volumeSolicitado,
        Collection $fontes,
        bool $distribuirEntreSelecionadas = false,
        bool $incluirFontesSemConsumo = false,
        array $consumosPreferenciais = [],
    ): array
    {
        $especie = Especie::find($especieId);
        $estado = $this->criarEstadoEspecie($fontes, $especie);
        return $this->montarPlanoConsumoDoEstado(
            $especieId,
            $volumeSolicitado,
            $estado,
            $distribuirEntreSelecionadas,
            $incluirFontesSemConsumo,
            $consumosPreferenciais,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $fontesEstado
     */
    private function montarPlanoConsumoDoEstado(
        string $especieId,
        float $volumeSolicitado,
        array $fontesEstado,
        bool $distribuirEntreSelecionadas = false,
        bool $incluirFontesSemConsumo = false,
        array $consumosPreferenciais = [],
    ): array
    {
        $volumeDisponivel = array_reduce($fontesEstado, fn (float $carry, array $fonte) => $carry + (float) $fonte['volume_disponivel_m3'], 0.0);
        $produtosDisponiveisPorLote = $this->montarProdutosDisponiveisPorLote($fontesEstado);
        $consumosFixos = [];
        foreach ($consumosPreferenciais as $consumoPreferencial) {
            $dofLoteId = (string) ($consumoPreferencial['dof_lote_id'] ?? '');
            $volumeConsumo = round((float) ($consumoPreferencial['volume_m3'] ?? 0), 4);
            if ($dofLoteId === '' || $volumeConsumo <= self::TOLERANCIA_VOLUME) {
                continue;
            }
            $consumosFixos[$dofLoteId] = ($consumosFixos[$dofLoteId] ?? 0.0) + $volumeConsumo;
        }

        $volumeFixado = 0.0;
        foreach ($consumosFixos as $fonteId => $volumeConsumo) {
            $fonteCorrespondente = null;
            foreach ($fontesEstado as $fonteEstado) {
                if ((string) $fonteEstado['dof_lote_id'] === $fonteId) {
                    $fonteCorrespondente = $fonteEstado;
                    break;
                }
            }

            if (!$fonteCorrespondente) {
                throw new \DomainException('Fonte de consumo manual inválida para a espécie selecionada.');
            }

            $disponivelFonte = (float) $fonteCorrespondente['volume_disponivel_m3'];
            if ($volumeConsumo > $disponivelFonte + self::TOLERANCIA_VOLUME) {
                throw new \DomainException('Volume manual informado acima do disponível em uma das fontes selecionadas.');
            }

            $volumeFixado += $volumeConsumo;
        }

        if ($volumeFixado > $volumeSolicitado + self::TOLERANCIA_VOLUME) {
            throw new \DomainException('A soma dos volumes manuais excede o volume solicitado do item.');
        }

        if ($volumeDisponivel + self::TOLERANCIA_VOLUME < $volumeSolicitado) {
            $especie = Especie::find($especieId);
            $nomeEspecie = $especie?->nome_formatado ?: ($especie?->nome_popular ?: $especie?->nome_cientifico ?: 'Espécie informada');

            throw new \DomainException(
                "Saldo insuficiente para {$nomeEspecie}. Disponível: " . number_format($volumeDisponivel, 4, '.', '') . " m³, solicitado: " . number_format($volumeSolicitado, 4, '.', '') . " m³."
            );
        }

        $restante = round($volumeSolicitado - $volumeFixado, 4);
        $fontesPlano = [];
        $volumeOrigemPecas = 0.0;
        $volumeOrigemManual = 0.0;
        $ajusteNecessarioItem = false;
        $consumoDistribuido = $distribuirEntreSelecionadas
            ? $this->distribuirConsumoEntreFontes(
                array_filter($fontesEstado, fn (array $fonte) => !isset($consumosFixos[(string) $fonte['dof_lote_id']])),
                $restante
            )
            : [];

        foreach ($fontesEstado as $idx => $fonteEstado) {
            if ($restante <= self::TOLERANCIA_VOLUME && !$incluirFontesSemConsumo) {
                break;
            }

            $disponivel = (float) $fonteEstado['volume_disponivel_m3'];
            if ($disponivel <= self::TOLERANCIA_VOLUME) {
                continue;
            }

            $fonteId = (string) $fonteEstado['dof_lote_id'];
            $consumo = isset($consumosFixos[$fonteId])
                ? min((float) $consumosFixos[$fonteId], $disponivel)
                : (isset($consumoDistribuido[$idx])
                    ? min((float) $consumoDistribuido[$idx], $disponivel)
                    : min($restante, $disponivel));
            $consumo = round(max(0.0, $consumo), 4);

            $modoAlocacao = (string) $fonteEstado['modo_alocacao'];
            $fontePlano = [
                'dof_lote_id' => $fonteEstado['dof_lote_id'],
                'dof_id' => $fonteEstado['dof_id'],
                'dof_item_id' => $fonteEstado['dof_item_id'],
                'lote_id' => $fonteEstado['lote_id'],
                'patio_nome' => $fonteEstado['patio_nome'] ?? null,
                'lote_nome' => $fonteEstado['lote_nome'] ?? null,
                'modo_alocacao' => $modoAlocacao,
                'volume_consumo_m3' => $consumo,
                'ajuste_necessario' => false,
                'produtos' => [],
                'produtos_lote' => $produtosDisponiveisPorLote[(string) $fonteEstado['lote_id']] ?? [],
            ];

            if ($consumo <= self::TOLERANCIA_VOLUME) {
                if ($incluirFontesSemConsumo && $modoAlocacao === DofAlocacao::MODO_PECAS) {
                    $sugestaoZero = $this->gerarSugestaoProdutosDaFonte($fonteEstado['produtos'], 0.0);
                    $fontePlano['produtos'] = $sugestaoZero['produtos'];
                    $fontesPlano[] = $fontePlano;
                }
                continue;
            }

            if ($modoAlocacao === DofAlocacao::MODO_PECAS) {
                $sugestao = $this->gerarSugestaoProdutosDaFonte($fonteEstado['produtos'], $consumo);
                $fontePlano['produtos'] = $sugestao['produtos'];
                $fontePlano['ajuste_necessario'] = $sugestao['ajuste_necessario'];
                $ajusteNecessarioItem = $ajusteNecessarioItem || $sugestao['ajuste_necessario'];
                $volumeOrigemPecas += $consumo;
            } else {
                $volumeOrigemManual += $consumo;
            }

            $fontesPlano[] = $fontePlano;

            $fontesEstado[$idx]['volume_disponivel_m3'] = round($disponivel - $consumo, 4);
            if (!isset($consumosFixos[$fonteId])) {
                $restante = round($restante - $consumo, 4);
            }
        }

        if ($restante > self::TOLERANCIA_VOLUME) {
            throw new \DomainException('Não foi possível compor o plano de consumo para a saída solicitada.');
        }

        $planoToken = $this->gerarTokenPlano([
            'especie_id' => $especieId,
            'volume_solicitado_m3' => round($volumeSolicitado, 4),
            'fontes' => $fontesPlano,
        ]);

        return [
            'volume_disponivel_m3' => round($volumeDisponivel, 4),
            'volume_origem_pecas_m3' => round($volumeOrigemPecas, 4),
            'volume_origem_manual_m3' => round($volumeOrigemManual, 4),
            'ajuste_necessario' => $ajusteNecessarioItem,
            'plano_token' => $planoToken,
            'fontes' => $fontesPlano,
            'estado_atualizado' => $fontesEstado,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $fontesEstado
     * @return array<int, float>
     */
    private function distribuirConsumoEntreFontes(array $fontesEstado, float $volumeSolicitado): array
    {
        if ($volumeSolicitado <= self::TOLERANCIA_VOLUME || empty($fontesEstado)) {
            return [];
        }

        $consumos = [];
        $ativos = [];

        foreach ($fontesEstado as $idx => $fonte) {
            $disponivel = (float) ($fonte['volume_disponivel_m3'] ?? 0);
            $consumos[$idx] = 0.0;
            if ($disponivel > self::TOLERANCIA_VOLUME) {
                $ativos[] = $idx;
            }
        }

        $restante = (float) $volumeSolicitado;

        while ($restante > self::TOLERANCIA_VOLUME && !empty($ativos)) {
            $cota = $restante / count($ativos);
            $houveProgresso = false;
            $proximosAtivos = [];

            foreach ($ativos as $idx) {
                $disponivel = (float) ($fontesEstado[$idx]['volume_disponivel_m3'] ?? 0);
                $disponivelRestante = max(0.0, $disponivel - $consumos[$idx]);
                if ($disponivelRestante <= self::TOLERANCIA_VOLUME) {
                    continue;
                }

                $alocar = min($cota, $disponivelRestante);
                if ($alocar <= self::TOLERANCIA_VOLUME) {
                    continue;
                }

                $consumos[$idx] += $alocar;
                $restante -= $alocar;
                $houveProgresso = true;

                if (($disponivelRestante - $alocar) > self::TOLERANCIA_VOLUME) {
                    $proximosAtivos[] = $idx;
                }
            }

            if (!$houveProgresso) {
                break;
            }

            $ativos = $proximosAtivos;
        }

        $consumosArredondados = [];
        $soma = 0.0;
        foreach ($consumos as $idx => $consumo) {
            $valor = round($consumo, 4);
            $consumosArredondados[$idx] = $valor;
            $soma += $valor;
        }
        $soma = round($soma, 4);
        $restanteAjuste = round($volumeSolicitado - $soma, 4);

        if (abs($restanteAjuste) > self::TOLERANCIA_VOLUME) {
            $indices = array_keys($consumosArredondados);
            usort($indices, function (int $a, int $b) use ($consumosArredondados): int {
                return $consumosArredondados[$b] <=> $consumosArredondados[$a];
            });

            foreach ($indices as $idx) {
                $disponivel = (float) ($fontesEstado[$idx]['volume_disponivel_m3'] ?? 0);
                $atual = (float) ($consumosArredondados[$idx] ?? 0);

                if ($restanteAjuste > 0) {
                    $margem = round($disponivel - $atual, 4);
                    if ($margem <= self::TOLERANCIA_VOLUME) {
                        continue;
                    }
                    $delta = min($margem, $restanteAjuste);
                    $consumosArredondados[$idx] = round($atual + $delta, 4);
                    $restanteAjuste = round($restanteAjuste - $delta, 4);
                } else {
                    if ($atual <= self::TOLERANCIA_VOLUME) {
                        continue;
                    }
                    $delta = min($atual, abs($restanteAjuste));
                    $consumosArredondados[$idx] = round($atual - $delta, 4);
                    $restanteAjuste = round($restanteAjuste + $delta, 4);
                }

                if (abs($restanteAjuste) <= self::TOLERANCIA_VOLUME) {
                    break;
                }
            }
        }

        return $consumosArredondados;
    }

    /**
     * @param array<int, array<string, mixed>> $fontesEstado
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function montarProdutosDisponiveisPorLote(array $fontesEstado): array
    {
        $agregadoPorLote = [];

        foreach ($fontesEstado as $fonte) {
            $loteId = (string) ($fonte['lote_id'] ?? '');
            if ($loteId === '') {
                continue;
            }

            if (!isset($agregadoPorLote[$loteId])) {
                $agregadoPorLote[$loteId] = [];
            }

            foreach (($fonte['produtos'] ?? []) as $produto) {
                $produtoId = (string) ($produto['produto_dimensionado_id'] ?? '');
                if ($produtoId === '') {
                    continue;
                }

                if (!isset($agregadoPorLote[$loteId][$produtoId])) {
                    $agregadoPorLote[$loteId][$produtoId] = [
                        'produto_dimensionado_id' => $produtoId,
                        'produto_nome' => (string) ($produto['produto_nome'] ?? ''),
                        'quantidade_disponivel' => 0,
                        'volume_unitario_m3' => round((float) ($produto['volume_unitario_m3'] ?? 0), 6),
                        'volume_disponivel_m3' => 0.0,
                    ];
                }

                $agregadoPorLote[$loteId][$produtoId]['quantidade_disponivel'] += (int) ($produto['quantidade_disponivel'] ?? 0);
                $agregadoPorLote[$loteId][$produtoId]['volume_disponivel_m3'] += (float) ($produto['volume_disponivel_m3'] ?? 0);
            }
        }

        $resultado = [];
        foreach ($agregadoPorLote as $loteId => $produtosMap) {
            $produtos = array_values($produtosMap);
            usort($produtos, fn (array $a, array $b) => strcmp((string) $a['produto_nome'], (string) $b['produto_nome']));

            $resultado[$loteId] = array_map(function (array $produto): array {
                $produto['volume_disponivel_m3'] = round((float) ($produto['volume_disponivel_m3'] ?? 0), 4);
                return $produto;
            }, $produtos);
        }

        return $resultado;
    }

    /**
     * @param array<int, array<string, mixed>> $produtosDisponiveis
     * @return array{produtos: array<int, array<string, mixed>>, ajuste_necessario: bool}
     */
    private function gerarSugestaoProdutosDaFonte(array $produtosDisponiveis, float $volumeConsumir): array
    {
        $restante = round($volumeConsumir, 4);
        $produtos = [];

        foreach ($produtosDisponiveis as $produto) {
            $volumeUnitario = (float) $produto['volume_unitario_m3'];
            $quantidadeDisponivel = (int) $produto['quantidade_disponivel'];
            $quantidadeSugerida = 0;

            if ($volumeUnitario > 0 && $quantidadeDisponivel > 0 && $restante > self::TOLERANCIA_VOLUME) {
                $maxPorVolume = (int) floor(($restante + self::TOLERANCIA_VOLUME) / $volumeUnitario);
                $quantidadeSugerida = max(0, min($quantidadeDisponivel, $maxPorVolume));
                $restante = round($restante - ($quantidadeSugerida * $volumeUnitario), 4);
            }

            $produtos[] = [
                'produto_dimensionado_id' => $produto['produto_dimensionado_id'],
                'produto_nome' => $produto['produto_nome'],
                'quantidade_disponivel' => $quantidadeDisponivel,
                'volume_unitario_m3' => round($volumeUnitario, 6),
                'volume_disponivel_m3' => round((float) $produto['volume_disponivel_m3'], 4),
                'quantidade_sugerida' => $quantidadeSugerida,
                'volume_sugerido_m3' => round($quantidadeSugerida * $volumeUnitario, 4),
            ];
        }

        return [
            'produtos' => $produtos,
            'ajuste_necessario' => $restante > self::TOLERANCIA_VOLUME,
        ];
    }

    /**
     * @param array<string, mixed> $itemPayload
     * @param array<string, mixed> $planoItem
     * @return array<string, array<string, int>>
     */
    private function validarBaixaProdutosDoItem(array $itemPayload, array $planoItem): array
    {
        $fontesPecas = collect($planoItem['fontes'])
            ->filter(fn (array $fonte) => (string) $fonte['modo_alocacao'] === DofAlocacao::MODO_PECAS)
            ->values();

        if ($fontesPecas->isEmpty()) {
            return [];
        }

        $baixaProdutos = $itemPayload['baixa_produtos'] ?? null;
        if (!is_array($baixaProdutos)) {
            throw new \DomainException('Informe a baixa de produtos dimensionados para as fontes em modo PEÇAS.');
        }

        $planoToken = (string) ($baixaProdutos['plano_token'] ?? '');
        if ($planoToken === '' || $planoToken !== (string) $planoItem['plano_token']) {
            throw new \DomainException('Plano de baixa de produtos desatualizado. Atualize o preview antes de registrar a saída.');
        }

        $fontesPayload = $baixaProdutos['fontes'] ?? null;
        if (!is_array($fontesPayload)) {
            throw new \DomainException('Informe as linhas de produtos por fonte para concluir a saída em modo PEÇAS.');
        }

        $fontesMap = [];
        foreach ($fontesPayload as $fontePayload) {
            $dofLoteId = (string) ($fontePayload['dof_lote_id'] ?? '');
            if ($dofLoteId === '') {
                throw new \DomainException('Fonte de baixa de produtos inválida.');
            }

            if (isset($fontesMap[$dofLoteId])) {
                throw new \DomainException('Fonte de baixa de produtos duplicada para o mesmo dof_lote.');
            }

            $linhas = $fontePayload['linhas'] ?? null;
            if (!is_array($linhas)) {
                throw new \DomainException('Linhas de produtos inválidas para uma das fontes selecionadas.');
            }

            $normalizadas = [];
            foreach ($linhas as $linha) {
                $produtoId = (string) ($linha['produto_dimensionado_id'] ?? '');
                $quantidade = (int) ($linha['quantidade_pecas'] ?? 0);

                if ($produtoId === '' || $quantidade <= 0) {
                    throw new \DomainException('Linha de baixa de produto inválida.');
                }

                $normalizadas[$produtoId] = ($normalizadas[$produtoId] ?? 0) + $quantidade;
            }

            $fontesMap[$dofLoteId] = $normalizadas;
        }

        $linhasPorFonteValidada = [];
        $volumeTotalInformadoPecas = 0.0;
        foreach ($fontesPecas as $fontePecas) {
            $dofLoteId = (string) $fontePecas['dof_lote_id'];
            $linhasSolicitadas = $fontesMap[$dofLoteId] ?? [];

            $produtosDisponiveis = [];
            foreach ($fontePecas['produtos'] as $produtoPlano) {
                $produtoId = (string) $produtoPlano['produto_dimensionado_id'];
                if ($produtoId === '') {
                    continue;
                }
                $produtosDisponiveis[$produtoId] = [
                    'quantidade_disponivel' => (int) $produtoPlano['quantidade_disponivel'],
                    'volume_unitario_m3' => (float) $produtoPlano['volume_unitario_m3'],
                    'produto_nome' => (string) $produtoPlano['produto_nome'],
                ];
            }

            $volumeInformado = 0.0;
            foreach ($linhasSolicitadas as $produtoId => $quantidadeSolicitada) {
                $produto = $produtosDisponiveis[$produtoId] ?? null;
                if (!$produto) {
                    throw new \DomainException('Produto informado não pertence à fonte selecionada para baixa.');
                }

                if ($quantidadeSolicitada > $produto['quantidade_disponivel']) {
                    throw new \DomainException(
                        "Quantidade solicitada acima do disponível para o produto '{$produto['produto_nome']}'."
                    );
                }

                $volumeInformado += $quantidadeSolicitada * (float) $produto['volume_unitario_m3'];
            }

            $volumeTotalInformadoPecas += $volumeInformado;

            if (!empty($linhasSolicitadas)) {
                $linhasPorFonteValidada[$dofLoteId] = $linhasSolicitadas;
            }
        }

        $volumeEsperadoPecas = (float) $fontesPecas
            ->sum(fn (array $fonte): float => (float) ($fonte['volume_consumo_m3'] ?? 0));

        if (!$this->volumesSaoIguais($volumeTotalInformadoPecas, $volumeEsperadoPecas)) {
            throw new \DomainException(
                'As linhas de produtos não fecham o volume total do item nas fontes em modo PEÇAS.'
            );
        }

        $fontesEsperadas = $fontesPecas->map(fn (array $fonte) => (string) $fonte['dof_lote_id'])->values()->all();
        foreach (array_keys($fontesMap) as $dofLoteId) {
            if (!in_array($dofLoteId, $fontesEsperadas, true)) {
                throw new \DomainException('Fonte de baixa de produtos não corresponde ao plano atual de consumo.');
            }
        }

        return $linhasPorFonteValidada;
    }

    /**
     * @param array<string, int> $linhasSolicitadas
     * @return array{volume_total_m3: float, produtos: array<int, array<string, mixed>>}
     */
    private function debitarLinhasAlocacaoPorPecas(DofLote $fonte, array $linhasSolicitadas): array
    {
        $alocacao = $fonte->alocacao;
        if (!$alocacao || $alocacao->modo_alocacao !== DofAlocacao::MODO_PECAS) {
            throw new \DomainException('Fonte informada não possui alocação por peças válida para débito.');
        }

        if (!$alocacao->relationLoaded('linhas')) {
            $alocacao->load(['linhas' => function ($query) {
                $query->orderBy('ordem')->orderBy('created_at');
            }]);
        }

        $linhas = $alocacao->linhas instanceof Collection
            ? $alocacao->linhas
            : collect($alocacao->linhas);

        $grupos = $linhas
            ->filter(fn (DofAlocacaoLinha $linha) => !empty($linha->produto_dimensionado_id))
            ->groupBy(fn (DofAlocacaoLinha $linha) => (string) $linha->produto_dimensionado_id);

        $produtosConsumidos = [];
        $volumeTotal = 0.0;

        foreach ($linhasSolicitadas as $produtoId => $quantidadeSolicitada) {
            /** @var Collection<int, DofAlocacaoLinha>|null $linhasProduto */
            $linhasProduto = $grupos->get($produtoId);
            if (!$linhasProduto || $linhasProduto->isEmpty()) {
                throw new \DomainException('Produto selecionado não encontrado na alocação da fonte consumida.');
            }

            $quantidadeDisponivel = (int) $linhasProduto->sum('quantidade_pecas');
            if ($quantidadeSolicitada > $quantidadeDisponivel) {
                throw new \DomainException('Quantidade solicitada acima do disponível para o produto selecionado.');
            }

            $linhaReferencia = $linhasProduto->first();
            if (!$linhaReferencia) {
                throw new \DomainException('Não foi possível resolver os dados da linha de produto para baixa.');
            }

            $volumeUnitario = (float) $linhaReferencia->volume_unitario_m3;
            $volumeProduto = round($volumeUnitario * $quantidadeSolicitada, 4);
            $volumeTotal += $volumeProduto;

            $produtosConsumidos[] = [
                'produto_dimensionado_id' => (string) $produtoId,
                'quantidade_pecas' => $quantidadeSolicitada,
                'volume_unitario_m3' => round($volumeUnitario, 6),
                'volume_total_m3' => $volumeProduto,
                'produto_nome_snapshot' => (string) $linhaReferencia->produto_nome_snapshot,
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
        }

        $this->reordenarLinhasAlocacao($alocacao);
        $this->recalcularCabecalhoPorLinhas($alocacao);

        return [
            'volume_total_m3' => round($volumeTotal, 4),
            'produtos' => $produtosConsumidos,
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

    private function removerDofLoteComAlocacao(DofLote $dofLote): void
    {
        $alocacao = $dofLote->relationLoaded('alocacao')
            ? $dofLote->alocacao
            : DofAlocacao::where('dof_lote_id', $dofLote->id)->first();

        if ($alocacao) {
            $alocacao->delete();
        }

        $dofLote->delete();
    }

    /**
     * @param array<int, array<string, mixed>> $fontesEstado
     */
    private function gerarTokenPlano(array $dados): string
    {
        $normalizado = [
            'especie_id' => (string) ($dados['especie_id'] ?? ''),
            'volume_solicitado_m3' => number_format((float) ($dados['volume_solicitado_m3'] ?? 0), 4, '.', ''),
            'fontes' => array_map(function (array $fonte): array {
                return [
                    'dof_lote_id' => (string) ($fonte['dof_lote_id'] ?? ''),
                    'modo_alocacao' => (string) ($fonte['modo_alocacao'] ?? ''),
                    'volume_consumo_m3' => number_format((float) ($fonte['volume_consumo_m3'] ?? 0), 4, '.', ''),
                ];
            }, $dados['fontes'] ?? []),
        ];

        return hash('sha256', json_encode($normalizado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param Collection<int, DofLote> $fontes
     * @return array<int, array<string, mixed>>
     */
    private function criarEstadoEspecie(Collection $fontes, ?Especie $especie = null): array
    {
        return $fontes
            ->map(fn (DofLote $fonte) => $this->montarEstadoFonte($fonte, $especie))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function montarEstadoFonte(DofLote $fonte, ?Especie $especie = null): array
    {
        $alocacao = $fonte->alocacao;
        $modoAlocacao = $alocacao?->modo_alocacao === DofAlocacao::MODO_PECAS
            ? DofAlocacao::MODO_PECAS
            : DofAlocacao::MODO_MANUAL;

        return [
            'dof_lote_id' => (string) $fonte->id,
            'dof_id' => (string) $fonte->dof_id,
            'dof_item_id' => $fonte->dof_item_id ? (string) $fonte->dof_item_id : null,
            'lote_id' => (string) $fonte->lote_id,
            'patio_nome' => $fonte->lote?->patio?->nome,
            'lote_nome' => $fonte->lote?->nome,
            'modo_alocacao' => $modoAlocacao,
            'volume_disponivel_m3' => round((float) $fonte->volume_m3, 4),
            '_ordem_base' => 0,
            'produtos' => $modoAlocacao === DofAlocacao::MODO_PECAS
                ? $this->montarProdutosDisponiveisDaFonte($fonte, $especie)
                : [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function montarProdutosDisponiveisDaFonte(DofLote $fonte, ?Especie $especie = null): array
    {
        $alocacao = $fonte->alocacao;
        if (!$alocacao || $alocacao->modo_alocacao !== DofAlocacao::MODO_PECAS) {
            return [];
        }

        if (!$alocacao->relationLoaded('linhas')) {
            $alocacao->load(['linhas' => function ($query) {
                $query->orderBy('ordem')->orderBy('created_at');
            }]);
        }

        $tipoEspecieNormalizado = null;
        $nomePopularNormalizado = null;
        if ($especie) {
            $tipoEspecieNormalizado = ProdutoDimensionadoEspecieMatcher::normalizarTipo($especie->resolverTipoSerragemNome());
            $nomePopularNormalizado = ProdutoDimensionadoEspecieMatcher::normalizarTexto($especie->nome_popular);
        }

        $produtosMap = [];
        foreach ($alocacao->linhas as $linha) {
            $produtoId = $linha->produto_dimensionado_id ? (string) $linha->produto_dimensionado_id : '';
            if ($produtoId === '') {
                continue;
            }

            if ($tipoEspecieNormalizado && $nomePopularNormalizado) {
                $tipoDofLinha = ProdutoDimensionadoEspecieMatcher::normalizarTipo($linha->tipo_dof_snapshot);
                $nomeProdutoNormalizado = ProdutoDimensionadoEspecieMatcher::normalizarTexto($linha->produto_nome_snapshot);

                if ($tipoDofLinha !== $tipoEspecieNormalizado) {
                    continue;
                }

                if (!str_contains($nomeProdutoNormalizado, $nomePopularNormalizado)) {
                    continue;
                }
            }

            if (!isset($produtosMap[$produtoId])) {
                $produtosMap[$produtoId] = [
                    'produto_dimensionado_id' => $produtoId,
                    'produto_nome' => (string) $linha->produto_nome_snapshot,
                    'quantidade_disponivel' => 0,
                    'volume_unitario_m3' => round((float) $linha->volume_unitario_m3, 6),
                    'volume_disponivel_m3' => 0.0,
                    '_ordem' => (int) $linha->ordem,
                ];
            }

            $quantidadeLinha = (int) $linha->quantidade_pecas;
            $produtosMap[$produtoId]['quantidade_disponivel'] += $quantidadeLinha;
            $produtosMap[$produtoId]['volume_disponivel_m3'] += (float) $linha->volume_total_m3;
            $produtosMap[$produtoId]['_ordem'] = min((int) $produtosMap[$produtoId]['_ordem'], (int) $linha->ordem);
        }

        $produtos = array_values($produtosMap);
        usort($produtos, fn (array $a, array $b) => ((int) $a['_ordem'] <=> (int) $b['_ordem']) ?: strcmp((string) $a['produto_nome'], (string) $b['produto_nome']));

        return array_map(function (array $produto): array {
            unset($produto['_ordem']);
            $produto['volume_disponivel_m3'] = round((float) $produto['volume_disponivel_m3'], 4);
            return $produto;
        }, $produtos);
    }

    /**
     * @return Collection<int, DofLote>
     */
    private function carregarFontesEspecie(string $especieId, bool $forUpdate, array $fontesPreferidas = []): Collection
    {
        $query = DofLote::query()
            ->with([
                'dof',
                'dofItem',
                'lote.patio',
                'alocacao' => function ($alocacaoQuery) {
                    $alocacaoQuery->with(['linhas' => function ($linhasQuery) {
                        $linhasQuery->orderBy('ordem')->orderBy('created_at');
                    }]);
                },
            ])
            ->join('dofs', 'dofs.id', '=', 'dof_lotes.dof_id')
            ->join('dof_itens', 'dof_itens.id', '=', 'dof_lotes.dof_item_id')
            ->where('dof_itens.especie_id', $especieId)
            ->where('dof_lotes.volume_m3', '>', 0)
            ->orderByRaw('CASE WHEN dofs.data_emissao IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('dofs.data_emissao', 'asc')
            ->orderBy('dofs.created_at', 'asc')
            ->orderBy('dof_lotes.created_at', 'asc')
            ->select('dof_lotes.*');

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $fontes = $query->get();
        return $this->reordenarColecaoPorFontesPreferidas($fontes, $fontesPreferidas);
    }

    private function volumesSaoIguais(float $valorA, float $valorB): bool
    {
        return abs(round($valorA, 4) - round($valorB, 4)) <= self::TOLERANCIA_VOLUME;
    }

    /**
     * @param Collection<int, DofLote> $fontes
     * @param array<int, string> $fontesPreferidas
     * @return Collection<int, DofLote>
     */
    private function reordenarColecaoPorFontesPreferidas(Collection $fontes, array $fontesPreferidas): Collection
    {
        if (empty($fontesPreferidas) || $fontes->isEmpty()) {
            return $fontes;
        }

        $fontesPorId = $fontes->keyBy(fn (DofLote $fonte) => (string) $fonte->id);
        $fontesSelecionadas = [];

        foreach (array_values(array_unique($fontesPreferidas)) as $fonteId) {
            /** @var DofLote|null $fonte */
            $fonte = $fontesPorId->get($fonteId);
            if (!$fonte) {
                throw new \DomainException('Fonte preferida inválida para a espécie selecionada ou sem saldo disponível.');
            }

            $fontesSelecionadas[] = $fonte;
        }

        return collect($fontesSelecionadas)->values();
    }

    /**
     * @param array<int, array<string, mixed>> $estado
     * @param array<int, string> $fontesPreferidas
     * @return array<int, array<string, mixed>>
     */
    private function reordenarEstadoPorPreferidas(array $estado, array $fontesPreferidas): array
    {
        if (empty($fontesPreferidas) || empty($estado)) {
            return array_values($estado);
        }

        $estadoPorId = [];
        foreach ($estado as $fonte) {
            $estadoPorId[(string) $fonte['dof_lote_id']] = $fonte;
        }

        $estadoSelecionado = [];
        foreach (array_values(array_unique($fontesPreferidas)) as $fonteId) {
            if (!isset($estadoPorId[$fonteId])) {
                throw new \DomainException('Fonte preferida inválida para a espécie selecionada ou sem saldo disponível.');
            }

            $estadoSelecionado[] = $estadoPorId[$fonteId];
        }

        return array_values($estadoSelecionado);
    }

    /**
     * @param array<int, array<string, mixed>> $estadoBase
     * @param array<int, array<string, mixed>> $estadoAtualizado
     * @return array<int, array<string, mixed>>
     */
    private function mesclarEstadoAtualizado(array $estadoBase, array $estadoAtualizado): array
    {
        if (empty($estadoBase)) {
            return array_values($estadoAtualizado);
        }

        if (empty($estadoAtualizado)) {
            return array_values($estadoBase);
        }

        $atualizadoPorId = [];
        foreach ($estadoAtualizado as $fonteAtualizada) {
            $atualizadoPorId[(string) $fonteAtualizada['dof_lote_id']] = $fonteAtualizada;
        }

        $estadoMesclado = [];
        foreach ($estadoBase as $fonteBase) {
            $fonteId = (string) $fonteBase['dof_lote_id'];
            $estadoMesclado[] = $atualizadoPorId[$fonteId] ?? $fonteBase;
        }

        return array_values($estadoMesclado);
    }

    /**
     * @param array<int, array<string, mixed>> $estado
     * @return array<int, array<string, mixed>>
     */
    private function montarFontesDisponiveis(array $estado): array
    {
        return array_map(function (array $fonte): array {
            return [
                'dof_lote_id' => (string) $fonte['dof_lote_id'],
                'dof_id' => (string) $fonte['dof_id'],
                'lote_id' => (string) $fonte['lote_id'],
                'patio_nome' => $fonte['patio_nome'] ?? null,
                'lote_nome' => $fonte['lote_nome'] ?? null,
                'modo_alocacao' => (string) $fonte['modo_alocacao'],
                'volume_disponivel_m3' => round((float) $fonte['volume_disponivel_m3'], 4),
                'produtos_count' => count($fonte['produtos'] ?? []),
            ];
        }, array_values($estado));
    }

    /**
     * @param mixed $fontesPreferidas
     * @return array<int, string>
     */
    private function normalizarFontesPreferidas(mixed $fontesPreferidas): array
    {
        if (!is_array($fontesPreferidas) || empty($fontesPreferidas)) {
            return [];
        }

        $normalizadas = [];
        foreach ($fontesPreferidas as $fonteId) {
            $id = trim((string) $fonteId);
            if ($id === '') {
                continue;
            }
            $normalizadas[] = $id;
        }

        return array_values(array_unique($normalizadas));
    }

    /**
     * @param mixed $fontesConsumo
     * @return array<int, array{dof_lote_id: string, volume_m3: float}>
     */
    private function normalizarFontesConsumo(mixed $fontesConsumo): array
    {
        if (!is_array($fontesConsumo) || empty($fontesConsumo)) {
            return [];
        }

        $normalizadas = [];
        foreach ($fontesConsumo as $fonteConsumo) {
            if (!is_array($fonteConsumo)) {
                continue;
            }

            $dofLoteId = trim((string) ($fonteConsumo['dof_lote_id'] ?? ''));
            $volume = round((float) ($fonteConsumo['volume_m3'] ?? 0), 4);
            if ($dofLoteId === '' || $volume <= self::TOLERANCIA_VOLUME) {
                continue;
            }

            $normalizadas[] = [
                'dof_lote_id' => $dofLoteId,
                'volume_m3' => $volume,
            ];
        }

        return $normalizadas;
    }
}
