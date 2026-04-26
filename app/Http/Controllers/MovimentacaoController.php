<?php

namespace App\Http\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Http\Resources\MovimentacaoResource;
use App\Services\MovimentacaoService;
use App\Services\SaidaService;
use App\Http\Requests\StoreSaidaRequest;
use App\Actions\ExportarRelatorioMovimentacoesPdfAction;
use App\Actions\ExportarRelatorioMovimentacoesExcelAction;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MovimentacaoController extends Controller
{
    public function __construct(
        private readonly MovimentacaoService $movimentacaoService,
        private readonly SaidaService $saidaService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $filtros = $request->only(['dof_id', 'lote_id', 'tipo', 'data_inicio', 'data_fim', 'busca', 'all']);
            $perPage = (int) $request->input('per_page', 15);

            $movimentacoes = $this->movimentacaoService->listar($filtros, $perPage);

            return ResponseHelper::successResponse(
                'MOVIMENTACOES_LISTADAS',
                MovimentacaoResource::collection($movimentacoes->items()),
                [
                    'pagina' => $movimentacoes->currentPage(),
                    'itens_por_pagina' => $movimentacoes->perPage(),
                    'total' => $movimentacoes->total(),
                ]
            );
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_LISTAR_MOVIMENTACOES', $e->getMessage(), 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $realId = AuthHelper::decryptId($id) ?? $id;
            $movimentacao = $this->movimentacaoService->buscarPorId($realId);
            return ResponseHelper::successResponse('Detalhes da movimentação', new MovimentacaoResource($movimentacao));
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('MOVIMENTACAO_NAO_ENCONTRADA', null, 404);
        }
    }

    public function porDof(string $dofId): JsonResponse
    {
        try {
            $realId = AuthHelper::decryptId($dofId) ?? $dofId;
            $movimentacoes = $this->movimentacaoService->listarPorDof($realId);
            return ResponseHelper::successResponse('MOVIMENTACOES_DO_DOF', MovimentacaoResource::collection($movimentacoes));
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_LISTAR_MOVIMENTACOES_DOF', $e->getMessage(), 500);
        }
    }

    public function porLote(Request $request, string $loteId): JsonResponse
    {
        try {
            $realId = AuthHelper::decryptId($loteId) ?? $loteId;
            $filtros = $request->only(['tipo', 'data_inicio', 'data_fim']);
            $movimentacoes = $this->movimentacaoService->listarPorLote($realId, $filtros);
            return ResponseHelper::successResponse('MOVIMENTACOES_DO_LOTE', MovimentacaoResource::collection($movimentacoes));
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_LISTAR_MOVIMENTACOES_LOTE', $e->getMessage(), 500);
        }
    }

    public function registrarSaida(StoreSaidaRequest $request): JsonResponse
    {
        try {
            $saida = $this->saidaService->registrarSaidaGlobal($request->validated());

            return ResponseHelper::successResponse('SAIDA_REGISTRADA_SUCESSO', $saida, [], 201);
        } catch (\DomainException $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_REGISTRAR_SAIDA', $e->getMessage(), 500);
        }
    }

    public function detalheSaida(string $id): JsonResponse
    {
        try {
            $realId = AuthHelper::decryptId($id) ?? $id;
            $saida = $this->saidaService->buscarSaidaOperacao($realId);
            return ResponseHelper::successResponse('Detalhes da saída', $saida); // Se tiver recurso para Saida, usar aqui.
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('SAIDA_NAO_ENCONTRADA', null, 404);
        }
    }

    public function previewSaida(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'especie_id' => 'required|uuid|exists:especies,id',
        ]);

        try {
            $preview = $this->saidaService->previewSaldoEspecie($validated['especie_id']);
            return ResponseHelper::successResponse('Preview saldo especie', $preview);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_PREVIEW_SAIDA', $e->getMessage(), 500);
        }
    }

    public function especiesDisponiveisSaida(): JsonResponse
    {
        try {
            $especies = $this->saidaService->listarEspeciesComSaldoDisponivel();
            return ResponseHelper::successResponse('Espécies disponíveis para saída', $especies);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_LISTAR_ESPECIES_DISPONIVEIS_SAIDA', $e->getMessage(), 500);
        }
    }

    public function previewProdutosEspecie(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'especie_id' => 'required|uuid|exists:especies,id',
        ]);

        try {
            $preview = $this->saidaService->previewProdutosEspecie($validated['especie_id']);
            return ResponseHelper::successResponse('Preview produtos especie', $preview);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_PREVIEW_PRODUTOS_ESPECIE', $e->getMessage(), 500);
        }
    }

    public function previewSaidaDimensionados(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'itens' => 'required|array|min:1',
            'itens.*.item_ref' => 'nullable|string|max:120',
            'itens.*.especie_id' => 'required|uuid|exists:especies,id',
            'itens.*.volume_m3' => 'required|numeric|min:0.0001',
            'itens.*.fontes_preferidas' => 'nullable|array',
            'itens.*.fontes_preferidas.*' => 'required_with:itens.*.fontes_preferidas|uuid|exists:dof_lotes,id',
            'itens.*.fontes_consumo' => 'nullable|array',
            'itens.*.fontes_consumo.*.dof_lote_id' => 'required_with:itens.*.fontes_consumo|uuid|exists:dof_lotes,id',
            'itens.*.fontes_consumo.*.volume_m3' => 'required_with:itens.*.fontes_consumo|numeric|min:0.0001',
        ]);

        try {
            $preview = $this->saidaService->previewSaidaDimensionados($validated);
            return ResponseHelper::successResponse('Preview saida', $preview);
        } catch (\DomainException $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_PREVIEW_SAIDA_DIMENSIONADOS', $e->getMessage(), 500);
        }
    }
    
    public function resumo(Request $request): JsonResponse
    {
        try {
            $filtros = $request->only(['dof_id', 'lote_id', 'tipo', 'data_inicio', 'data_fim', 'busca']);
            $resumo = $this->movimentacaoService->resumo($filtros);
            return ResponseHelper::successResponse('Resumo de Movimentações', $resumo);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_RESUMO_MOVIMENTACOES', $e->getMessage(), 500);
        }
    }

    public function relatorioPdf(Request $request, ExportarRelatorioMovimentacoesPdfAction $action): Response|JsonResponse
    {
        try {
            $filtros = $request->only(['dof_id', 'lote_id', 'tipo', 'data_inicio', 'data_fim', 'busca']);
            $movimentacoes = $this->movimentacaoService->listarParaRelatorio($filtros);
            $filtros['empresa_nome'] = $this->resolverNomeEmpresaRelatorio($request);
            return $action->execute($movimentacoes, $filtros);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_RELATORIO_MOVIMENTACOES_PDF', $e->getMessage(), 500);
        }
    }

    public function relatorioExcel(Request $request, ExportarRelatorioMovimentacoesExcelAction $action): StreamedResponse|JsonResponse
    {
        try {
            $filtros = $request->only(['dof_id', 'lote_id', 'tipo', 'data_inicio', 'data_fim', 'busca']);
            $movimentacoes = $this->movimentacaoService->listarParaRelatorio($filtros);
            $filtros['empresa_nome'] = $this->resolverNomeEmpresaRelatorio($request);
            return $action->execute($movimentacoes, $filtros);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_RELATORIO_MOVIMENTACOES_EXCEL', $e->getMessage(), 500);
        }
    }

    private function resolverNomeEmpresaRelatorio(Request $request): string
    {
        $empresaId = (string) ($request->get('empresa_id') ?: $request->user()?->empresa_id ?: '');

        if ($empresaId === '') {
            return '—';
        }

        return (string) (Empresa::query()->whereKey($empresaId)->value('nome') ?: '—');
    }
}
