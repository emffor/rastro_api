<?php

namespace App\Services;

use App\Models\DofLote;
use App\Models\Patio;
use App\Models\PatioAreaBloqueada;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class PatioService
{
    public function listar(): array
    {
        return Patio::with(['lotes' => function ($query) {
            $query->select('id', 'patio_id', 'codigo', 'nome', 'status', 'volume_ocupado');
        }])
        ->withCount('lotes')
        ->orderBy('nome')
        ->get()
        ->toArray();
    }

    public function buscar(string $id): Patio
    {
        return Patio::with(['lotes.dofLotes.dof', 'lotes.dofLotes.dofItem.especie', 'areasBloqueadas'])
            ->findOrFail($id);
    }

    public function buscarEstoquePecas(string $patioId): array
    {
        $patio = Patio::with(['lotes:id,patio_id,nome'])->findOrFail($patioId);

        $dofLotes = DofLote::with([
            'lote:id,patio_id,nome',
            'dofItem.especie:id,nome_cientifico,nome_popular,nome_tipo,tipo,tipo_serragem_id',
            'dofItem.especie.tipoSerragem:id,nome',
            'alocacao' => function ($query) {
                $query->with(['linhas' => function ($linhasQuery) {
                    $linhasQuery->orderBy('ordem');
                }]);
            },
        ])
            ->whereHas('lote', function ($query) use ($patioId) {
                $query->where('patio_id', $patioId);
            })
            ->get();

        $totalPecas = 0;
        $totalVolume = 0.0;
        $itensMap = [];
        $produtosMap = [];
        $lotesMap = [];

        foreach ($patio->lotes as $lote) {
            $lotesMap[(string) $lote->id] = [
                'lote_id' => (string) $lote->id,
                'lote_nome' => (string) $lote->nome,
                'total_pecas' => 0,
                'volume_total_m3' => 0.0,
                '_itens' => [],
                '_produtos' => [],
            ];
        }

        foreach ($dofLotes as $dofLote) {
            $alocacao = $dofLote->alocacao;
            if (!$alocacao || $alocacao->modo_alocacao !== 'PECAS') {
                continue;
            }
            $linhas = $alocacao->linhas ?? collect();
            if ($linhas->isEmpty()) {
                continue;
            }

            $loteId = (string) $dofLote->lote_id;
            if (!isset($lotesMap[$loteId])) {
                $lotesMap[$loteId] = [
                    'lote_id' => $loteId,
                    'lote_nome' => (string) ($dofLote->lote?->nome ?? 'Lote'),
                    'total_pecas' => 0,
                    'volume_total_m3' => 0.0,
                    '_itens' => [],
                    '_produtos' => [],
                ];
            }

            foreach ($linhas as $linha) {
                $quantidade = (int) $linha->quantidade_pecas;
                $volumeLinha = (float) $linha->volume_total_m3;
                $produtoId = $linha->produto_dimensionado_id ? (string) $linha->produto_dimensionado_id : null;
                $produtoKey = $produtoId ?: 'SEM_PRODUTO::' . (string) $linha->produto_nome_snapshot;

                $totalPecas += $quantidade;
                $totalVolume += $volumeLinha;

                $lotesMap[$loteId]['total_pecas'] += $quantidade;
                $lotesMap[$loteId]['volume_total_m3'] += $volumeLinha;
                $lotesMap[$loteId]['_produtos'][$produtoKey] = true;
                if ($dofLote->dof_item_id) {
                    $lotesMap[$loteId]['_itens'][(string) $dofLote->dof_item_id] = true;
                }

                if ($dofLote->dof_item_id) {
                    $itemKey = (string) $dofLote->dof_item_id;
                    if (!isset($itensMap[$itemKey])) {
                        $itensMap[$itemKey] = [
                            'dof_item_id' => $itemKey,
                            'especie_id' => $dofLote->dofItem?->especie_id,
                            'especie_nome' => $this->formatarNomeEspecie(
                                $dofLote->dofItem?->especie?->tipoSerragem?->nome
                                    ?? $dofLote->dofItem?->especie?->nome_tipo,
                                $dofLote->dofItem?->especie?->nome_cientifico,
                                $dofLote->dofItem?->especie?->nome_popular,
                            ),
                            'total_pecas' => 0,
                            'volume_total_m3' => 0.0,
                        ];
                    }

                    $itensMap[$itemKey]['total_pecas'] += $quantidade;
                    $itensMap[$itemKey]['volume_total_m3'] += $volumeLinha;
                }

                if (!isset($produtosMap[$produtoKey])) {
                    $produtosMap[$produtoKey] = [
                        'produto_dimensionado_id' => $produtoId,
                        'produto_nome' => (string) $linha->produto_nome_snapshot,
                        'total_pecas' => 0,
                        'volume_total_m3' => 0.0,
                    ];
                }
                $produtosMap[$produtoKey]['total_pecas'] += $quantidade;
                $produtosMap[$produtoKey]['volume_total_m3'] += $volumeLinha;
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

        $lotes = array_values(array_map(function (array $lote) {
            return [
                'lote_id' => $lote['lote_id'],
                'lote_nome' => $lote['lote_nome'],
                'total_pecas' => (int) $lote['total_pecas'],
                'volume_total_m3' => round((float) $lote['volume_total_m3'], 4),
                'itens_dof_count' => count($lote['_itens']),
                'produtos_dimensionados_count' => count($lote['_produtos']),
            ];
        }, $lotesMap));
        usort($lotes, fn (array $a, array $b) => strcmp((string) $a['lote_nome'], (string) $b['lote_nome']));

        return [
            'patio_id' => $patio->id,
            'total_pecas' => $totalPecas,
            'total_volume_m3' => round($totalVolume, 4),
            'itens_dof' => $itens,
            'produtos_dimensionados' => $produtos,
            'lotes' => $lotes,
        ];
    }

    public function criar(array $dados): Patio
    {
        return DB::transaction(function () use ($dados) {
            $patio = Patio::create([
                'nome' => $dados['nome'],
                'descricao' => $dados['descricao'] ?? null,
                'endereco' => $dados['endereco'] ?? null,
                'largura' => isset($dados['largura_metros']) ? ($dados['largura_metros'] * 40) : 500,
                'altura' => isset($dados['comprimento_metros']) ? ($dados['comprimento_metros'] * 40) : 600,
                'cor_fundo' => $dados['cor_fundo'] ?? '#4CAF50',
                'configuracao_mapa' => $dados['configuracao_mapa'] ?? null,
                'ativo' => $dados['ativo'] ?? true,
                'largura_metros' => $dados['largura_metros'] ?? null,
                'comprimento_metros' => $dados['comprimento_metros'] ?? null,
                'altura_metros' => $dados['altura_metros'] ?? null,
            ]);

            Log::info('Pátio criado', ['patio_id' => $patio->id, 'nome' => $patio->nome]);

            return $patio;
        });
    }

    public function atualizar(string $id, array $dados): Patio
    {
        return DB::transaction(function () use ($id, $dados) {
            $patio = Patio::findOrFail($id);

            $patio->update([
                'nome' => $dados['nome'] ?? $patio->nome,
                'descricao' => $dados['descricao'] ?? $patio->descricao,
                'endereco' => $dados['endereco'] ?? $patio->endereco,
                'largura' => isset($dados['largura_metros']) ? ($dados['largura_metros'] * 40) : $patio->largura,
                'altura' => isset($dados['comprimento_metros']) ? ($dados['comprimento_metros'] * 40) : $patio->altura,
                'cor_fundo' => $dados['cor_fundo'] ?? $patio->cor_fundo,
                'configuracao_mapa' => $dados['configuracao_mapa'] ?? $patio->configuracao_mapa,
                'ativo' => $dados['ativo'] ?? $patio->ativo,
                'largura_metros' => $dados['largura_metros'] ?? $patio->largura_metros,
                'comprimento_metros' => $dados['comprimento_metros'] ?? $patio->comprimento_metros,
                'altura_metros' => array_key_exists('altura_metros', $dados) ? $dados['altura_metros'] : $patio->altura_metros,
            ]);

            Log::info('Pátio atualizado', ['patio_id' => $patio->id]);

            return $patio->fresh();
        });
    }

    public function excluir(string $id): void
    {
        DB::transaction(function () use ($id) {
            $patio = Patio::findOrFail($id);

            $lotesComDofs = $patio->lotes()->whereHas('dofLotes')->count();
            if ($lotesComDofs > 0) {
                throw new Exception("Não é possível excluir pátio com lotes que possuem DOFs alocados.");
            }

            $patio->lotes()->delete();
            $patio->delete();

            Log::info('Pátio excluído', ['patio_id' => $id]);
        });
    }

    public function salvarConfiguracaoMapa(string $id, array $configuracao): Patio
    {
        $patio = Patio::findOrFail($id);
        $patio->configuracao_mapa = $configuracao;
        $patio->save();

        Log::info('Configuração do mapa atualizada', ['patio_id' => $id]);

        return $patio;
    }

    public function listarAreasBloqueadas(string $patioId): array
    {
        return PatioAreaBloqueada::where('patio_id', $patioId)
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }

    public function criarAreaBloqueada(string $patioId, array $dados): PatioAreaBloqueada
    {
        return DB::transaction(function () use ($patioId, $dados) {
            Patio::findOrFail($patioId);

            $area = PatioAreaBloqueada::create([
                'patio_id' => $patioId,
                'nome' => $dados['nome'] ?? null,
                'pos_x' => $dados['pos_x'] ?? 0,
                'pos_y' => $dados['pos_y'] ?? 0,
                'largura' => $dados['largura'] ?? 50,
                'altura' => $dados['altura'] ?? 50,
                'cor' => $dados['cor'] ?? '#CCCCCC',
            ]);

            Log::info('Área bloqueada criada', [
                'area_id' => $area->id,
                'patio_id' => $patioId,
            ]);

            return $area;
        });
    }

    public function atualizarAreaBloqueada(string $id, array $dados): PatioAreaBloqueada
    {
        return DB::transaction(function () use ($id, $dados) {
            $area = PatioAreaBloqueada::findOrFail($id);

            $area->update([
                'nome' => $dados['nome'] ?? $area->nome,
                'pos_x' => $dados['pos_x'] ?? $area->pos_x,
                'pos_y' => $dados['pos_y'] ?? $area->pos_y,
                'largura' => $dados['largura'] ?? $area->largura,
                'altura' => $dados['altura'] ?? $area->altura,
                'cor' => $dados['cor'] ?? $area->cor,
            ]);

            Log::info('Área bloqueada atualizada', ['area_id' => $id]);

            return $area->fresh();
        });
    }

    public function excluirAreaBloqueada(string $id): void
    {
        $area = PatioAreaBloqueada::findOrFail($id);
        $area->delete();

        Log::info('Área bloqueada excluída', ['area_id' => $id]);
    }

    public function salvarAreasBloqueadasEmLote(string $patioId, array $areas): array
    {
        return DB::transaction(function () use ($patioId, $areas) {
            $patio = Patio::findOrFail($patioId);
            $areasExistentes = PatioAreaBloqueada::where('patio_id', $patioId)->get()->keyBy('id');

            $retangulosAreas = [];
            foreach ($areas as $areaData) {
                $existente = !empty($areaData['id']) ? $areasExistentes->get($areaData['id']) : null;
                $retangulosAreas[] = [
                    'id' => (string) ($areaData['id'] ?? ''),
                    'nome' => (string) ($areaData['nome'] ?? ($existente?->nome ?: 'Área sem nome')),
                    'x' => (float) ($areaData['pos_x'] ?? ($existente?->pos_x ?? 0)),
                    'y' => (float) ($areaData['pos_y'] ?? ($existente?->pos_y ?? 0)),
                    'w' => (float) ($areaData['largura'] ?? ($existente?->largura ?? 50)),
                    'h' => (float) ($areaData['altura'] ?? ($existente?->altura ?? 50)),
                ];
            }

            $retangulosLotes = $patio->lotes()
                ->get()
                ->map(function ($lote) {
                    $x = (float) $lote->pos_x / 40;
                    $y = (float) $lote->pos_y / 40;
                    $w = (float) $lote->largura / 40;
                    $h = (float) $lote->altura / 40;
                    $rotRaw = ((float) $lote->rotacao % 360 + 360) % 360;
                    $rot = ((round($rotRaw / 90) * 90) + 360) % 360;

                    if ($rot === 90.0) {
                        return [
                            'id' => $lote->id,
                            'nome' => $lote->nome ?: 'Lote sem nome',
                            'x' => $x - $h,
                            'y' => $y,
                            'w' => $h,
                            'h' => $w,
                        ];
                    }

                    if ($rot === 180.0) {
                        return [
                            'id' => $lote->id,
                            'nome' => $lote->nome ?: 'Lote sem nome',
                            'x' => $x - $w,
                            'y' => $y - $h,
                            'w' => $w,
                            'h' => $h,
                        ];
                    }

                    if ($rot === 270.0) {
                        return [
                            'id' => $lote->id,
                            'nome' => $lote->nome ?: 'Lote sem nome',
                            'x' => $x,
                            'y' => $y - $w,
                            'w' => $h,
                            'h' => $w,
                        ];
                    }

                    return [
                        'id' => $lote->id,
                        'nome' => $lote->nome ?: 'Lote sem nome',
                        'x' => $x,
                        'y' => $y,
                        'w' => $w,
                        'h' => $h,
                    ];
                })
                ->toArray();

            $this->validarLayoutAreas($patio, $retangulosAreas, $retangulosLotes);

            $idsRecebidos = array_filter(array_column($areas, 'id'));
            
            PatioAreaBloqueada::where('patio_id', $patioId)
                ->whereNotIn('id', $idsRecebidos)
                ->delete();

            $areasProcessadas = [];

            foreach ($areas as $areaData) {
                if (!empty($areaData['id'])) {
                    $area = PatioAreaBloqueada::where('patio_id', $patioId)
                        ->where('id', $areaData['id'])
                        ->first();

                    if ($area) {
                        $area->update([
                            'nome' => $areaData['nome'] ?? $area->nome,
                            'pos_x' => $areaData['pos_x'] ?? $area->pos_x,
                            'pos_y' => $areaData['pos_y'] ?? $area->pos_y,
                            'largura' => $areaData['largura'] ?? $area->largura,
                            'altura' => $areaData['altura'] ?? $area->altura,
                            'cor' => $areaData['cor'] ?? $area->cor,
                        ]);
                        $areasProcessadas[] = $area->fresh();
                    }
                } else {
                    $area = PatioAreaBloqueada::create([
                        'patio_id' => $patioId,
                        'nome' => $areaData['nome'] ?? null,
                        'pos_x' => $areaData['pos_x'] ?? 0,
                        'pos_y' => $areaData['pos_y'] ?? 0,
                        'largura' => $areaData['largura'] ?? 50,
                        'altura' => $areaData['altura'] ?? 50,
                        'cor' => $areaData['cor'] ?? '#CCCCCC',
                    ]);
                    $areasProcessadas[] = $area;
                }
            }

            Log::info('Áreas bloqueadas salvas em lote', [
                'patio_id' => $patioId,
                'quantidade' => count($areasProcessadas),
            ]);

            return $areasProcessadas;
        });
    }

    private function validarLayoutAreas(Patio $patio, array $retangulosAreas, array $retangulosLotes): void
    {
        [$patioW, $patioH] = $this->getDimensoesPatioMetros($patio);

        foreach ($retangulosAreas as $area) {
            if (!$this->isInsidePatio($area, $patioW, $patioH)) {
                $this->throwColisao("Área '{$area['nome']}' fora dos limites do pátio.");
            }
        }

        $totalAreas = count($retangulosAreas);
        for ($i = 0; $i < $totalAreas; $i++) {
            for ($j = $i + 1; $j < $totalAreas; $j++) {
                if ($this->intersects($retangulosAreas[$i], $retangulosAreas[$j])) {
                    $this->throwColisao("Área '{$retangulosAreas[$i]['nome']}' sobrepõe área '{$retangulosAreas[$j]['nome']}'.");
                }
            }
        }

        foreach ($retangulosAreas as $area) {
            foreach ($retangulosLotes as $lote) {
                if ($this->intersects($area, $lote)) {
                    $this->throwColisao("Área '{$area['nome']}' sobrepõe lote '{$lote['nome']}'.");
                }
            }
        }
    }

    private function getDimensoesPatioMetros(Patio $patio): array
    {
        $w = $patio->largura_metros !== null ? (float) $patio->largura_metros : ((float) $patio->largura / 40);
        $h = $patio->comprimento_metros !== null ? (float) $patio->comprimento_metros : ((float) $patio->altura / 40);
        return [$w, $h];
    }

    private function intersects(array $a, array $b): bool
    {
        return
            $a['x'] < $b['x'] + $b['w'] &&
            $a['x'] + $a['w'] > $b['x'] &&
            $a['y'] < $b['y'] + $b['h'] &&
            $a['y'] + $a['h'] > $b['y'];
    }

    private function isInsidePatio(array $rect, float $patioW, float $patioH): bool
    {
        return
            $rect['x'] >= 0 &&
            $rect['y'] >= 0 &&
            $rect['x'] + $rect['w'] <= $patioW &&
            $rect['y'] + $rect['h'] <= $patioH;
    }

    private function throwColisao(string $detalhe): void
    {
        throw new Exception('COLISAO_LAYOUT_PATIO::' . $detalhe);
    }

    private function formatarNomeEspecie(?string $nomeTipo, ?string $cientifico, ?string $popular): string
    {
        $tipo = trim((string) $nomeTipo);
        $nomeCientifico = trim((string) $cientifico);
        $nomePopular = trim((string) $popular);

        if ($tipo !== '' && $nomeCientifico !== '' && $nomePopular !== '') {
            return "{$tipo} / {$nomeCientifico} - {$nomePopular}";
        }

        if ($nomePopular !== '') {
            return $nomePopular;
        }

        return $nomeCientifico !== '' ? $nomeCientifico : 'Sem espécie';
    }
}
