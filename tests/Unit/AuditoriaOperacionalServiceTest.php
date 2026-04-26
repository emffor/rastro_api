<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\AuditoriaOperacionalService;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

class AuditoriaOperacionalServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_registra_requisicao_com_metadados_seguros(): void
    {
        $user = new User([
            'name' => 'Usuário Teste',
            'email' => 'usuario@teste.com',
            'empresa_id' => 'empresa-1',
        ]);
        $user->id = 'usuario-1';
        $user->withAccessToken((object) [
            'id' => 10,
            'name' => 'auth-token',
        ]);

        $request = Request::create('/api/dofs/abc', 'PUT', [
            'empresa_id' => 'empresa-1',
            'password' => 'segredo',
            'token' => 'token-bruto',
        ]);
        $request->setUserResolver(fn () => $user);

        $auditoria = Mockery::mock(AuditoriaService::class);
        $auditoria
            ->shouldReceive('registrar')
            ->once()
            ->withArgs(function (
                string $logName,
                string $evento,
                mixed $entidade,
                array $propriedades,
                Request $requestRecebida,
                User $causador,
            ) use ($request, $user): bool {
                return $logName === 'requisicoes_empresa'
                    && $evento === 'requisicao_sucesso'
                    && $entidade === null
                    && $requestRecebida === $request
                    && $causador === $user
                    && $propriedades['acao'] === 'editar'
                    && $propriedades['metodo'] === 'PUT'
                    && $propriedades['path'] === 'api/dofs/abc'
                    && $propriedades['status_code'] === 200
                    && $propriedades['empresa_id'] === 'empresa-1'
                    && $propriedades['usuario_id'] === 'usuario-1'
                    && $propriedades['token_name'] === 'auth-token'
                    && ! array_key_exists('password', $propriedades)
                    && ! array_key_exists('token', $propriedades);
            });

        $service = new AuditoriaOperacionalService($auditoria);
        $service->registrarRequisicaoEmpresaSucesso($request, new JsonResponse(['ok' => true], 200));
    }
}
