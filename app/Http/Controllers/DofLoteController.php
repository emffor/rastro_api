<?php

namespace App\Http\Controllers;

use App\Helpers\AuthHelper;
use App\Models\Dof;
use App\Models\DofAlocacao;
use App\Services\DofLoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DofLoteController extends Controller
{
    public function __construct(
        private readonly DofLoteService $dofLoteService,
    ) {}

    public function porDof(string $dofId): JsonResponse
    {
        try {
            $realDofId = $this->resolverId($dofId) ?? $dofId;
            $alocacoes = $this->dofLoteService->listarPorDof($realDofId);
            $dados = $alocacoes->map(fn ($alocacao) => $this->formatarDofLoteResposta($alocacao))->values();
            return response()->json([
                'dados' => $dados,
                'resumo' => $this->dofLoteService->montarResumoConsolidado($alocacoes),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['mensagem' => 'ERRO_LISTAR_ALOCACOES', 'erro' => $e->getMessage()], 500);
        }
    }

    public function porLote(string $loteId): JsonResponse
    {
        try {
            $realLoteId = $this->resolverId($loteId) ?? $loteId;
            $alocacoes = $this->dofLoteService->listarPorLote($realLoteId);
            $dados = $alocacoes->map(fn ($alocacao) => $this->formatarDofLoteResposta($alocacao))->values();
            return response()->json([
                'dados' => $dados,
                'resumo' => $this->dofLoteService->montarResumoConsolidado($alocacoes),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['mensagem' => 'ERRO_LISTAR_ALOCACOES_LOTE', 'erro' => $e->getMessage()], 500);
        }
    }

    public function alocar(Request $request): JsonResponse
    {
        $this->normalizarIdsAlocacao($request);

        $validated = $request->validate([
            'dof_item_id' => 'nullable|uuid|exists:dof_itens,id|required_without:dof_id|required_with:linhas',
            'dof_id' => 'nullable|uuid|exists:dofs,id|required_without:dof_item_id',
            'lote_id' => 'required|uuid|exists:lotes,id',
            'volume_m3' => 'nullable|numeric|min:0.0001|required_without:linhas',
            'linhas' => 'nullable|array|min:1|required_without:volume_m3',
            'linhas.*.produto_dimensionado_id' => 'required_with:linhas|uuid|exists:produtos_dimensionados,id',
            'linhas.*.quantidade_pecas' => 'required_with:linhas|integer|min:1',
            'observacao' => 'nullable|string|max:500',
        ]);

        try {
            $dofItemId = $validated['dof_item_id'] ?? null;
            $isAlocacaoPorPecas = !empty($validated['linhas']);

            if ($isAlocacaoPorPecas) {
                $dofLote = $this->dofLoteService->alocarPorPecas(
                    dofItemId: (string) $dofItemId,
                    loteId: $validated['lote_id'],
                    linhas: $validated['linhas'],
                    observacao: $validated['observacao'] ?? null,
                );
            } else {
                // Compatibilidade com payload antigo (dof_id) para evitar quebra imediata.
                // Se houver múltiplos itens elegíveis, exige seleção explícita de dof_item_id.
                if (!$dofItemId && !empty($validated['dof_id'])) {
                    $volumeSolicitado = (float) $validated['volume_m3'];
                    $dof = Dof::with('itens')->findOrFail($validated['dof_id']);

                    $itensElegiveis = $dof->itens
                        ->filter(fn ($item) => (float) $item->quantidade_disponivel >= $volumeSolicitado)
                        ->values();

                    if ($itensElegiveis->count() === 1) {
                        $dofItemId = (string) $itensElegiveis->first()->id;
                    } elseif ($itensElegiveis->isEmpty()) {
                        throw new \DomainException('Nenhum item do DOF possui saldo disponível para o volume informado.');
                    } else {
                        throw new \DomainException('DOF com múltiplos itens. Informe dof_item_id para alocar corretamente.');
                    }
                }

                $dofLote = $this->dofLoteService->alocar(
                    dofItemId: (string) $dofItemId,
                    loteId: $validated['lote_id'],
                    volumeM3: (float) $validated['volume_m3'],
                    observacao: $validated['observacao'] ?? null,
                );
            }

            return response()->json([
                'mensagem' => 'ALOCACAO_CRIADA_SUCESSO',
                'dados' => $this->formatarDofLoteResposta($dofLote),
            ], 201);
        } catch (\DomainException $e) {
            return response()->json(['mensagem' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['mensagem' => 'ERRO_ALOCAR_DOF', 'erro' => $e->getMessage()], 500);
        }
    }

    public function detalheAlocacao(string $id): JsonResponse
    {
        try {
            $realId = $this->resolverId($id) ?? $id;
            $detalhe = $this->dofLoteService->detalharAlocacaoPorDofLote($realId);
            return response()->json(['dados' => $detalhe]);
        } catch (\DomainException) {
            return response()->json(['mensagem' => 'DETALHE_ALOCACAO_NAO_ENCONTRADO'], 404);
        } catch (\Throwable $e) {
            return response()->json(['mensagem' => 'ERRO_DETALHAR_ALOCACAO', 'erro' => $e->getMessage()], 500);
        }
    }

    public function transferir(Request $request): JsonResponse
    {
        $this->normalizarIdsTransferencia($request);

        $validated = $request->validate([
            'dof_lote_id' => 'required|uuid|exists:dof_lotes,id',
            'lote_destino_id' => 'required|uuid|exists:lotes,id',
            'volume_m3' => 'nullable|numeric|min:0.0001',
            'linhas' => 'nullable|array|min:1',
            'linhas.*.produto_dimensionado_id' => 'required_with:linhas|uuid|exists:produtos_dimensionados,id',
            'linhas.*.quantidade_pecas' => 'required_with:linhas|integer|min:1',
            'observacao' => 'nullable|string|max:500',
        ]);

        try {
            $dofLote = $this->dofLoteService->transferir(
                dofLoteId: $validated['dof_lote_id'],
                loteDestinoId: $validated['lote_destino_id'],
                volumeM3: isset($validated['volume_m3']) ? (float) $validated['volume_m3'] : null,
                linhas: $validated['linhas'] ?? null,
                observacao: $validated['observacao'] ?? null,
            );

            return response()->json([
                'mensagem' => 'TRANSFERENCIA_SUCESSO',
                'dados' => $this->formatarDofLoteResposta($dofLote),
            ]);
        } catch (\DomainException $e) {
            return response()->json(['mensagem' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['mensagem' => 'ERRO_TRANSFERIR_DOF', 'erro' => $e->getMessage()], 500);
        }
    }

    public function baixa(Request $request): JsonResponse
    {
        $this->normalizarIdsBaixa($request);

        $validated = $request->validate([
            'dof_lote_id' => 'required|uuid|exists:dof_lotes,id',
            'volume_m3' => 'nullable|numeric|min:0.0001',
            'linhas' => 'nullable|array|min:1',
            'linhas.*.produto_dimensionado_id' => 'required_with:linhas|uuid|exists:produtos_dimensionados,id',
            'linhas.*.quantidade_pecas' => 'required_with:linhas|integer|min:1',
            'observacao' => 'nullable|string|max:500',
        ]);

        try {
            $this->dofLoteService->baixa(
                dofLoteId: $validated['dof_lote_id'],
                volumeM3: isset($validated['volume_m3']) ? (float) $validated['volume_m3'] : null,
                linhas: $validated['linhas'] ?? null,
                observacao: $validated['observacao'] ?? null,
            );

            return response()->json(['mensagem' => 'BAIXA_SUCESSO']);
        } catch (\DomainException $e) {
            return response()->json(['mensagem' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['mensagem' => 'ERRO_BAIXA_DOF', 'erro' => $e->getMessage()], 500);
        }
    }

    public function remover(string $id): JsonResponse
    {
        try {
            $realId = $this->resolverId($id) ?? $id;
            $this->dofLoteService->removerAlocacao($realId);

            return response()->json(['mensagem' => 'ALOCACAO_REMOVIDA_SUCESSO']);
        } catch (\DomainException $e) {
            return response()->json(['mensagem' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['mensagem' => 'ERRO_REMOVER_ALOCACAO', 'erro' => $e->getMessage()], 500);
        }
    }

    private function formatarDofLoteResposta(object $dofLote): array
    {
        $dados = $dofLote->toArray();
        $alocacao = $dofLote->alocacao ?? null;

        if ($alocacao) {
            $dados['modo_alocacao'] = $alocacao->modo_alocacao;
            $dados['total_pecas'] = (int) $alocacao->total_pecas;
            $dados['linhas_count'] = (int) ($alocacao->linhas_count ?? ($alocacao->linhas?->count() ?? 0));
            $dados['resumo_pecas'] = $this->dofLoteService->montarResumoPecasDoDofLote($dofLote);
            return $dados;
        }

        $dados['modo_alocacao'] = DofAlocacao::MODO_MANUAL;
        $dados['total_pecas'] = 0;
        $dados['linhas_count'] = 0;
        $dados['resumo_pecas'] = [
            'total_pecas' => 0,
            'total_volume_m3' => 0.0,
            'produtos' => [],
        ];
        return $dados;
    }

    private function normalizarIdsAlocacao(Request $request): void
    {
        $linhas = $request->input('linhas');
        if (is_array($linhas)) {
            $linhasNormalizadas = array_map(function ($linha): array {
                if (!is_array($linha)) {
                    return [];
                }
                if (!empty($linha['produto_dimensionado_id'])) {
                    $linha['produto_dimensionado_id'] = $this->resolverId($linha['produto_dimensionado_id']) ?? $linha['produto_dimensionado_id'];
                }

                return $linha;
            }, $linhas);
            $request->merge(['linhas' => $linhasNormalizadas]);
        }

        $request->merge([
            'dof_item_id' => $this->resolverId($request->input('dof_item_id')) ?? $request->input('dof_item_id'),
            'dof_id' => $this->resolverId($request->input('dof_id')) ?? $request->input('dof_id'),
            'lote_id' => $this->resolverId($request->input('lote_id')) ?? $request->input('lote_id'),
        ]);
    }

    private function normalizarIdsTransferencia(Request $request): void
    {
        $linhas = $request->input('linhas');
        if (is_array($linhas)) {
            $linhasNormalizadas = array_map(function ($linha): array {
                if (!is_array($linha)) {
                    return [];
                }
                if (!empty($linha['produto_dimensionado_id'])) {
                    $linha['produto_dimensionado_id'] = $this->resolverId($linha['produto_dimensionado_id']) ?? $linha['produto_dimensionado_id'];
                }

                return $linha;
            }, $linhas);
            $request->merge(['linhas' => $linhasNormalizadas]);
        }

        $request->merge([
            'dof_lote_id' => $this->resolverId($request->input('dof_lote_id')) ?? $request->input('dof_lote_id'),
            'lote_destino_id' => $this->resolverId($request->input('lote_destino_id')) ?? $request->input('lote_destino_id'),
        ]);
    }

    private function normalizarIdsBaixa(Request $request): void
    {
        $linhas = $request->input('linhas');
        if (is_array($linhas)) {
            $linhasNormalizadas = array_map(function ($linha): array {
                if (!is_array($linha)) {
                    return [];
                }
                if (!empty($linha['produto_dimensionado_id'])) {
                    $linha['produto_dimensionado_id'] = $this->resolverId($linha['produto_dimensionado_id']) ?? $linha['produto_dimensionado_id'];
                }

                return $linha;
            }, $linhas);
            $request->merge(['linhas' => $linhasNormalizadas]);
        }

        $request->merge([
            'dof_lote_id' => $this->resolverId($request->input('dof_lote_id')) ?? $request->input('dof_lote_id'),
        ]);
    }

    private function resolverId(?string $id): ?string
    {
        if (empty($id)) {
            return null;
        }

        return AuthHelper::decryptId($id) ?? $id;
    }
}
