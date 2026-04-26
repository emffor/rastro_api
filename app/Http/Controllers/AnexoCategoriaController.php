<?php

namespace App\Http\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Http\Requests\StoreAnexoCategoriaRequest;
use App\Http\Requests\UpdateAnexoCategoriaRequest;
use App\Http\Resources\AnexoCategoriaResource;
use App\Services\AnexoCategoriaService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AnexoCategoriaController extends Controller
{
    public function __construct(
        private readonly AnexoCategoriaService $anexoCategoriaService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $categorias = $this->anexoCategoriaService->listar();

            return ResponseHelper::successResponse('CATEGORIAS_LISTADAS_SUCESSO', AnexoCategoriaResource::collection($categorias));
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_LISTAR_CATEGORIAS', $e->getMessage(), 500);
        }
    }

    public function ativos(Request $request): JsonResponse
    {
        try {
            $categorias = $this->anexoCategoriaService->listarAtivos();

            return ResponseHelper::successResponse('CATEGORIAS_ATIVAS_LISTADAS_SUCESSO', AnexoCategoriaResource::collection($categorias));
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_LISTAR_CATEGORIAS_ATIVAS', $e->getMessage(), 500);
        }
    }

    public function store(StoreAnexoCategoriaRequest $request): JsonResponse
    {
        try {
            $categoria = $this->anexoCategoriaService->criar($request->validated());

            return ResponseHelper::successResponse('CATEGORIA_CRIADA_SUCESSO', new AnexoCategoriaResource($categoria), [], 201);
        } catch (HttpExceptionInterface $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, $e->getStatusCode());
        } catch (\DomainException $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_CRIAR_CATEGORIA', $e->getMessage(), 500);
        }
    }

    public function update(UpdateAnexoCategoriaRequest $request, string $id): JsonResponse
    {
        try {
            $categoria = $this->anexoCategoriaService->atualizar(
                $this->resolverId($id),
                $request->validated(),
            );

            return ResponseHelper::successResponse('CATEGORIA_ATUALIZADA_SUCESSO', new AnexoCategoriaResource($categoria));
        } catch (HttpExceptionInterface $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, $e->getStatusCode());
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::errorResponse('RECURSO_NAO_ENCONTRADO', null, 404);
        } catch (\DomainException $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_ATUALIZAR_CATEGORIA', $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->anexoCategoriaService->remover($this->resolverId($id));

            return ResponseHelper::successResponse('CATEGORIA_DESATIVADA_SUCESSO');
        } catch (HttpExceptionInterface $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, $e->getStatusCode());
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::errorResponse('RECURSO_NAO_ENCONTRADO', null, 404);
        } catch (\DomainException $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_DESATIVAR_CATEGORIA', $e->getMessage(), 500);
        }
    }

    private function resolverId(?string $id): string
    {
        return (string) (AuthHelper::decryptId($id) ?? $id);
    }
}
