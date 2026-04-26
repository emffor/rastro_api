<?php

namespace App\Http\Middleware;

use App\Services\AuditoriaOperacionalService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuditarRequisicaoEmpresa
{
    private const METODOS_ESCRITA = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(
        private readonly AuditoriaOperacionalService $auditoriaOperacionalService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->auditoriaOperacionalService->registrarRequisicaoEmpresaErro(
                $request,
                null,
                $exception,
            );

            throw $exception;
        }

        if ($this->deveRegistrarErro($response)) {
            $this->auditoriaOperacionalService->registrarRequisicaoEmpresaErro($request, $response);

            return $response;
        }

        if ($this->deveRegistrarSucesso($request, $response)) {
            $this->auditoriaOperacionalService->registrarRequisicaoEmpresaSucesso($request, $response);
        }

        return $response;
    }

    private function deveRegistrarErro(Response $response): bool
    {
        return $response->getStatusCode() >= 400;
    }

    private function deveRegistrarSucesso(Request $request, Response $response): bool
    {
        return in_array($request->method(), self::METODOS_ESCRITA, true)
            && $response->getStatusCode() < 400;
    }
}
