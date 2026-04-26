<?php

namespace App\Http\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Http\Requests\DeleteAnexoGenericoRequest;
use App\Http\Requests\UploadAnexoGenericoRequest;
use App\Http\Resources\AnexoResource;
use App\Services\AnexoGenericoService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AnexoGenericoController extends Controller
{
    public function __construct(
        private readonly AnexoGenericoService $anexoGenericoService,
    ) {}

    public function upload(UploadAnexoGenericoRequest $request): JsonResponse
    {
        try {
            $empresaId = $this->obterEmpresaIdAtual($request);
            $entidadeId = $this->resolverId($request->validated('entidade_id'));
            $entidade = $this->anexoGenericoService->buscarEntidade(
                (string) $request->validated('entidade_type'),
                $entidadeId,
                $empresaId,
            );

            $anexo = $this->anexoGenericoService->upload(
                $request->file('file'),
                $empresaId,
                (string) $request->validated('categoria_slug'),
                $entidade,
                $request->validated('campo'),
                $request->validated('observacao'),
                (string) ($request->validated('acao') ?? 'upload'),
            );

            return ResponseHelper::successResponse('ANEXO_ENVIADO_SUCESSO', new AnexoResource($anexo), [], 201);
        } catch (HttpExceptionInterface $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, $e->getStatusCode());
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::errorResponse('RECURSO_NAO_ENCONTRADO', null, 404);
        } catch (\DomainException $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_ENVIAR_ANEXO', $e->getMessage(), 500);
        }
    }

    public function deletar(DeleteAnexoGenericoRequest $request, string $relacionavelId): JsonResponse
    {
        try {
            $relacionavel = $this->anexoGenericoService->buscarRelacionavelPorId(
                $this->resolverId($relacionavelId),
                $this->obterEmpresaIdAtual($request),
            );
            $this->anexoGenericoService->deletar(
                $relacionavel,
                (string) $request->validated('observacao'),
                (string) $request->validated('acao'),
            );

            return ResponseHelper::successResponse('ANEXO_EXCLUIDO_SUCESSO');
        } catch (HttpExceptionInterface $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, $e->getStatusCode());
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::errorResponse('RECURSO_NAO_ENCONTRADO', null, 404);
        } catch (\DomainException $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_EXCLUIR_ANEXO', $e->getMessage(), 500);
        }
    }

    public function listarPorEntidade(Request $request): JsonResponse
    {
        try {
            $entidadeType = (string) $request->query('entidade_type');
            $entidadeId = $this->resolverId((string) $request->query('entidade_id'));

            $this->anexoGenericoService->buscarEntidade($entidadeType, $entidadeId, $this->obterEmpresaIdAtual($request));
            $anexos = $this->anexoGenericoService->listarPorEntidade($entidadeType, $entidadeId);

            return ResponseHelper::successResponse(
                'ANEXOS_LISTADOS_SUCESSO',
                AnexoResource::collection($anexos),
            );
        } catch (HttpExceptionInterface $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, $e->getStatusCode());
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::errorResponse('RECURSO_NAO_ENCONTRADO', null, 404);
        } catch (\DomainException $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_LISTAR_ANEXOS', $e->getMessage(), 500);
        }
    }

    public function obterUrl(Request $request, string $anexoId): JsonResponse
    {
        try {
            $url = $this->anexoGenericoService->obterUrlTemporaria($this->resolverId($anexoId));

            return ResponseHelper::successResponse('URL_ANEXO_OBTIDA_SUCESSO', [
                'url' => $url,
            ]);
        } catch (HttpExceptionInterface $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, $e->getStatusCode());
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::errorResponse('RECURSO_NAO_ENCONTRADO', null, 404);
        } catch (\DomainException $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_OBTER_URL_ANEXO', $e->getMessage(), 500);
        }
    }

    private function obterEmpresaIdAtual(Request $request): string
    {
        return (string) ($request->get('empresa_id') ?: $request->user()?->empresa_id);
    }

    private function resolverId(?string $id): string
    {
        return (string) (AuthHelper::decryptId($id) ?? $id);
    }
}
