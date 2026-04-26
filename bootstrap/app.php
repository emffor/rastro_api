<?php

use App\Services\AuditoriaOperacionalService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'empresa.scope' => \App\Http\Middleware\EmpresaScope::class,
            'auditoria.empresa' => \App\Http\Middleware\AuditarRequisicaoEmpresa::class,
            'permissao' => \App\Http\Middleware\CheckPermissao::class,
            'admin_master' => \App\Http\Middleware\CheckAdminMaster::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (Throwable $exception) {
            $request = request();

            if (!$request instanceof Request || !str_starts_with($request->path(), 'api/')) {
                return;
            }

            if (
                $exception instanceof ValidationException ||
                $exception instanceof AuthenticationException ||
                $exception instanceof AuthorizationException ||
                $exception instanceof HttpExceptionInterface
            ) {
                return;
            }

            app(AuditoriaOperacionalService::class)->registrarErroExecucao($exception, $request);
        });
    })->create();
