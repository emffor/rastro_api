<?php

namespace App\Http\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Http\Requests\StoreDofRequest;
use App\Http\Requests\UpdateDofRequest;
use App\Http\Resources\DofResource;
use App\Services\DofService;
use App\Actions\ExportarRelatorioDofsPdfAction;
use App\Actions\ExportarRelatorioDofsExcelAction;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DofController extends Controller
{
    public function __construct(
        private readonly DofService $dofService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $filtros = $request->only(['numero', 'busca', 'status', 'data_inicio', 'data_fim', 'all']);
            $perPage = (int) $request->input('per_page', 15);

            $dofs = $this->dofService->listar($filtros, $perPage);

            return ResponseHelper::successResponse(
                'DOFs listados com sucesso.',
                DofResource::collection($dofs->items()),
                [
                    'pagina' => $dofs->currentPage(),
                    'itens_por_pagina' => $dofs->perPage(),
                    'total' => $dofs->total(),
                ]
            );
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_LISTAR_DOFS', $e->getMessage(), 500);
        }
    }

    public function store(StoreDofRequest $request): JsonResponse
    {
        try {
            $dof = $this->dofService->criar($request->validated());
            return ResponseHelper::successResponse('DOF_CRIADO_SUCESSO', new DofResource($dof), [], 201);
        } catch (\DomainException $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_CRIAR_DOF', $e->getMessage(), 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $realId = AuthHelper::decryptId($id) ?? $id;
            $dof = $this->dofService->buscarPorId($realId);

            return ResponseHelper::successResponse('DOF_ENCONTRADO', [
                'dof' => new DofResource($dof),
                'volume_alocado' => $dof->getVolumeAlocadoAttribute(),
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('DOF_NAO_ENCONTRADO', null, 404);
        }
    }

    public function update(UpdateDofRequest $request, string $id): JsonResponse
    {
        try {
            $realId = AuthHelper::decryptId($id) ?? $id;
            $dof = $this->dofService->atualizar($realId, $request->validated());

            return ResponseHelper::successResponse('DOF_ATUALIZADO_SUCESSO', new DofResource($dof));
        } catch (\DomainException $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_ATUALIZAR_DOF', $e->getMessage(), 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $realId = AuthHelper::decryptId($id) ?? $id;
            $this->dofService->excluir($realId);
            return ResponseHelper::successResponse('DOF_EXCLUIDO_SUCESSO');
        } catch (\DomainException $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_EXCLUIR_DOF', $e->getMessage(), 500);
        }
    }

    public function ativos(): JsonResponse
    {
        try {
            $dofs = $this->dofService->listarAtivos();
            return ResponseHelper::successResponse('Ativos listados', DofResource::collection($dofs));
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_LISTAR_ATIVOS', $e->getMessage(), 500);
        }
    }

    public function resumo(Request $request): JsonResponse
    {
        try {
            $filtros = $request->only(['numero', 'busca', 'status', 'data_inicio', 'data_fim']);
            $resumo = $this->dofService->resumo($filtros);
            return ResponseHelper::successResponse('RESUMO_DOFS', $resumo);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_RESUMO_DOFS', $e->getMessage(), 500);
        }
    }

    public function relatorioPdf(Request $request, ExportarRelatorioDofsPdfAction $action): Response|JsonResponse
    {
        try {
            $filtros = $request->only(['numero', 'busca', 'status', 'data_inicio', 'data_fim']);
            $dofs = $this->dofService->listarParaRelatorio($filtros);
            $filtros['empresa_nome'] = $this->resolverNomeEmpresaRelatorio($request);
            return $action->execute($dofs, $filtros);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_RELATORIO_DOFS_PDF', $e->getMessage(), 500);
        }
    }

    public function relatorioExcel(Request $request, ExportarRelatorioDofsExcelAction $action): StreamedResponse|JsonResponse
    {
        try {
            $filtros = $request->only(['numero', 'busca', 'status', 'data_inicio', 'data_fim']);
            $dofs = $this->dofService->listarParaRelatorio($filtros);
            $filtros['empresa_nome'] = $this->resolverNomeEmpresaRelatorio($request);
            return $action->execute($dofs, $filtros);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_RELATORIO_DOFS_EXCEL', $e->getMessage(), 500);
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
