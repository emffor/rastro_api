<?php

namespace App\Http\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Http\Requests\UploadAnexoDofRequest;
use App\Http\Requests\UploadAnexoNfRequest;
use App\Models\SaidaOperacaoItemNota;
use App\Services\AnexoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AnexoController extends Controller
{
    public function __construct(
        private readonly AnexoService $anexoService,
    ) {}

    public function uploadNf(UploadAnexoNfRequest $request, string $saidaOperacaoItemNotaId): JsonResponse
    {
        try {
            $empresaId = $this->obterEmpresaIdAtual($request);
            $nota = $this->obterNotaFiscal($saidaOperacaoItemNotaId);
            $anexo = $this->anexoService->uploadAnexoNf(
                $request->file('arquivo'),
                $empresaId,
                $nota,
            );

            return ResponseHelper::successResponse('ANEXO_NF_ENVIADO_SUCESSO', $anexo);
        } catch (HttpExceptionInterface $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, $e->getStatusCode());
        } catch (\DomainException $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_ENVIAR_ANEXO_NF', $e->getMessage(), 500);
        }
    }

    public function uploadDof(UploadAnexoDofRequest $request, string $saidaOperacaoItemNotaId): JsonResponse
    {
        try {
            $empresaId = $this->obterEmpresaIdAtual($request);
            $nota = $this->obterNotaFiscal($saidaOperacaoItemNotaId);
            $anexo = $this->anexoService->uploadAnexoDof(
                $request->file('arquivo'),
                $empresaId,
                $nota,
            );

            return ResponseHelper::successResponse('ANEXO_DOF_ENVIADO_SUCESSO', $anexo);
        } catch (HttpExceptionInterface $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, $e->getStatusCode());
        } catch (\DomainException $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_ENVIAR_ANEXO_DOF', $e->getMessage(), 500);
        }
    }

    public function deletarNf(Request $request, string $saidaOperacaoItemNotaId): JsonResponse
    {
        try {
            $nota = $this->obterNotaFiscal($saidaOperacaoItemNotaId);
            $this->anexoService->deletarAnexoNf($nota);

            return ResponseHelper::successResponse('ANEXO_NF_EXCLUIDO_SUCESSO');
        } catch (HttpExceptionInterface $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, $e->getStatusCode());
        } catch (\DomainException $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_EXCLUIR_ANEXO_NF', $e->getMessage(), 500);
        }
    }

    public function deletarDof(Request $request, string $saidaOperacaoItemNotaId): JsonResponse
    {
        try {
            $nota = $this->obterNotaFiscal($saidaOperacaoItemNotaId);
            $this->anexoService->deletarAnexoDof($nota);

            return ResponseHelper::successResponse('ANEXO_DOF_EXCLUIDO_SUCESSO');
        } catch (HttpExceptionInterface $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, $e->getStatusCode());
        } catch (\DomainException $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_EXCLUIR_ANEXO_DOF', $e->getMessage(), 500);
        }
    }

    private function obterNotaFiscal(string $saidaOperacaoItemNotaId): SaidaOperacaoItemNota
    {
        $realId = AuthHelper::decryptId($saidaOperacaoItemNotaId) ?? $saidaOperacaoItemNotaId;
        $nota = SaidaOperacaoItemNota::with('saidaOperacaoItem.saidaOperacao')->findOrFail($realId);
        $empresaId = $this->obterEmpresaIdAtual(request());
        $empresaDaNota = (string) ($nota->saidaOperacaoItem?->saidaOperacao?->empresa_id ?? '');

        if ($empresaId === '' || $empresaDaNota === '' || $empresaId !== $empresaDaNota) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(403, 'Anexo não pertence à empresa logada.');
        }

        return $nota;
    }

    public function obterLimite(Request $request): JsonResponse
    {
        try {
            $empresaId = $this->obterEmpresaIdAtual($request);
            $categoriaSlug = $request->query('categoria');

            if ($categoriaSlug === null) {
                $limiteNf = $this->anexoService->obterLimiteAtual($empresaId, 'nf');
                $limiteDof = $this->anexoService->obterLimiteAtual($empresaId, 'dof');
                $limiteMensal = 10;
                $uploadsNf = (int) $limiteNf->uploads_usados;
                $uploadsDof = (int) $limiteDof->uploads_usados;

                return ResponseHelper::successResponse('LIMITE_OBTIDO', [
                    'uploads_nf_usados' => $uploadsNf,
                    'uploads_dof_usados' => $uploadsDof,
                    'uploads_nf_restantes' => max(0, $limiteMensal - $uploadsNf),
                    'uploads_dof_restantes' => max(0, $limiteMensal - $uploadsDof),
                    'uploads_nf_percentual' => min(100, round(($uploadsNf / $limiteMensal) * 100, 2)),
                    'uploads_dof_percentual' => min(100, round(($uploadsDof / $limiteMensal) * 100, 2)),
                    'mes_referencia' => $limiteNf->mes_referencia,
                ]);
            }

            $limite = $this->anexoService->obterLimiteAtual($empresaId, $categoriaSlug);
            $categoria = \App\Models\AnexoCategoria::obterPorSlug($categoriaSlug);

            return ResponseHelper::successResponse('LIMITE_OBTIDO', [
                'categoria' => $categoriaSlug,
                'limite_mensal' => $categoria?->limite_mensal_por_empresa,
                'uploads_usados' => $limite->uploads_usados,
                'pode_upload' => $limite->podeUpload($categoria?->limite_mensal_por_empresa),
                'mes_referencia' => $limite->mes_referencia,
            ]);
        } catch (\DomainException $e) {
            return ResponseHelper::errorResponse($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return ResponseHelper::errorResponse('ERRO_OBTER_LIMITE', $e->getMessage(), 500);
        }
    }

    private function obterEmpresaIdAtual(Request $request): string
    {
        return (string) ($request->get('empresa_id') ?: $request->user()?->empresa_id);
    }
}
