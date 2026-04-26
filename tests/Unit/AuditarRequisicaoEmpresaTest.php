<?php

namespace Tests\Unit;

use App\Http\Middleware\AuditarRequisicaoEmpresa;
use App\Services\AuditoriaOperacionalService;
use Illuminate\Http\Request;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

class AuditarRequisicaoEmpresaTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_nao_registra_get_com_sucesso(): void
    {
        $auditoria = Mockery::mock(AuditoriaOperacionalService::class);
        $auditoria->shouldNotReceive('registrarRequisicaoEmpresaSucesso');
        $auditoria->shouldNotReceive('registrarRequisicaoEmpresaErro');

        $middleware = new AuditarRequisicaoEmpresa($auditoria);
        $request = Request::create('/api/dashboard', 'GET');

        $response = $middleware->handle($request, fn () => new JsonResponse(['ok' => true], 200));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_registra_sucesso_para_metodo_de_escrita(): void
    {
        $auditoria = Mockery::mock(AuditoriaOperacionalService::class);
        $auditoria
            ->shouldReceive('registrarRequisicaoEmpresaSucesso')
            ->once();
        $auditoria->shouldNotReceive('registrarRequisicaoEmpresaErro');

        $middleware = new AuditarRequisicaoEmpresa($auditoria);
        $request = Request::create('/api/dofs', 'POST');

        $response = $middleware->handle($request, fn () => new JsonResponse(['ok' => true], 201));

        $this->assertSame(201, $response->getStatusCode());
    }

    public function test_registra_erro_para_resposta_com_falha(): void
    {
        $auditoria = Mockery::mock(AuditoriaOperacionalService::class);
        $auditoria->shouldNotReceive('registrarRequisicaoEmpresaSucesso');
        $auditoria
            ->shouldReceive('registrarRequisicaoEmpresaErro')
            ->once();

        $middleware = new AuditarRequisicaoEmpresa($auditoria);
        $request = Request::create('/api/dofs', 'GET');

        $response = $middleware->handle($request, fn () => new JsonResponse(['mensagem' => 'Erro'], 403));

        $this->assertSame(403, $response->getStatusCode());
    }
}
