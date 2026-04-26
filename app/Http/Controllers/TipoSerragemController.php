<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\TipoSerragemResource;
use App\Services\TipoSerragemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipoSerragemController extends Controller
{
    public function __construct(
        private readonly TipoSerragemService $tipoSerragemService,
    ) {}

    public function index(): JsonResponse
    {
        try {
            $tipos = $this->tipoSerragemService->listar();

            return ResponseHelper::successResponse(
                'TIPOS_SERRAGEM_LISTADOS_SUCESSO',
                TipoSerragemResource::collection($tipos)
            );
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_LISTAR_TIPOS_SERRAGEM', $e->getMessage(), 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:50',
        ]);

        try {
            $tipo = $this->tipoSerragemService->criar($validated);

            return ResponseHelper::successResponse(
                'TIPO_SERRAGEM_CRIADO_SUCESSO',
                new TipoSerragemResource($tipo),
                [],
                201
            );
        } catch (\DomainException $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_CRIAR_TIPO_SERRAGEM', $e->getMessage(), 500);
        }
    }
}
