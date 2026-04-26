<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuditoriaOperacionalService
{
    private const LOG_AUTENTICACAO = 'autenticacao';

    private const LOG_ERROS_EXECUCAO = 'erros_execucao';

    private const LOG_REQUISICOES_EMPRESA = 'requisicoes_empresa';

    private const LOG_SESSAO_EMPRESA = 'sessao_empresa';

    public function __construct(
        private readonly AuditoriaService $auditoriaService,
    ) {}

    public function registrarLoginRealizado(User $user, Request $request): void
    {
        $user->loadMissing('empresa');

        $this->registrarSeguro(
            self::LOG_AUTENTICACAO,
            'login_realizado',
            $user,
            $this->propriedadesUsuario($user, $request),
            $request,
            $user,
        );
    }

    public function registrarLogoutRealizado(User $user, Request $request): void
    {
        $user->loadMissing('empresa');

        $this->registrarSeguro(
            self::LOG_AUTENTICACAO,
            'logout_realizado',
            $user,
            $this->propriedadesUsuario($user, $request),
            $request,
            $user,
        );
    }

    public function registrarLoginFalhou(Request $request, string $motivo, ?User $user = null): void
    {
        $user?->loadMissing('empresa');

        $this->registrarSeguro(
            self::LOG_AUTENTICACAO,
            'login_falhou',
            $user,
            array_filter([
                'email' => $this->normalizarEmail($request),
                'ip' => $request->ip(),
                'motivo' => $motivo,
                'empresa_id' => $request->input('empresa_id'),
                'usuario_id' => $user?->id,
                'usuario_nome' => $user?->name,
                'usuario_email' => $user?->email,
                'usuario_empresa_id' => $user?->empresa_id,
                'usuario_empresa_nome' => $user?->empresa?->nome,
            ], fn ($valor) => $valor !== null && $valor !== ''),
            $request,
            $user,
        );
    }

    public function registrarErroExecucao(Throwable $exception, Request $request): void
    {
        $user = $request->user();

        $this->registrarSeguro(
            self::LOG_ERROS_EXECUCAO,
            'erro_execucao',
            null,
            array_filter([
                'mensagem' => 'Erro interno de execução.',
                'exception' => $exception::class,
                'metodo' => $request->method(),
                'path' => $request->path(),
                'rota' => $request->route()?->getName(),
                'ip' => $request->ip(),
                'usuario_id' => $user?->id,
                'usuario_nome' => $user?->name,
                'usuario_email' => $user?->email,
                'empresa_id' => $user?->empresa_id,
            ], fn ($valor) => $valor !== null && $valor !== ''),
            $request,
            $user,
        );
    }

    public function registrarRequisicaoEmpresaSucesso(Request $request, Response $response): void
    {
        $this->registrarSeguro(
            self::LOG_REQUISICOES_EMPRESA,
            'requisicao_sucesso',
            null,
            $this->propriedadesRequisicao($request, $response->getStatusCode()),
            $request,
            $request->user(),
        );
    }

    public function registrarRequisicaoEmpresaErro(Request $request, ?Response $response = null, ?Throwable $exception = null): void
    {
        $this->registrarSeguro(
            self::LOG_REQUISICOES_EMPRESA,
            'requisicao_erro',
            null,
            $this->propriedadesRequisicao(
                $request,
                $response?->getStatusCode() ?? 500,
                $exception,
            ),
            $request,
            $request->user(),
        );
    }

    public function registrarSessaoEmpresa(Request $request, string $evento, ?string $empresaId = null): void
    {
        $this->registrarSeguro(
            self::LOG_SESSAO_EMPRESA,
            $evento,
            null,
            array_filter([
                'metodo' => $request->method(),
                'path' => $request->path(),
                'rota' => $request->route()?->getName(),
                'ip' => $request->ip(),
                'empresa_id' => $empresaId ?? $request->input('empresa_id'),
                'usuario_id' => $request->user()?->id,
                'usuario_nome' => $request->user()?->name,
                'usuario_email' => $request->user()?->email,
                'token_id' => $request->user()?->currentAccessToken()?->id,
                'token_name' => $request->user()?->currentAccessToken()?->name,
            ], fn ($valor) => $valor !== null && $valor !== ''),
            $request,
            $request->user(),
        );
    }

    private function propriedadesUsuario(User $user, Request $request): array
    {
        return array_filter([
            'usuario_id' => $user->id,
            'usuario_nome' => $user->name,
            'usuario_email' => $user->email,
            'email' => $user->email,
            'ip' => $request->ip(),
            'empresa_id' => $user->empresa_id,
            'empresa_nome' => $user->empresa?->nome,
        ], fn ($valor) => $valor !== null && $valor !== '');
    }

    private function normalizarEmail(Request $request): ?string
    {
        $email = trim((string) $request->input('email', ''));

        return $email === '' ? null : strtolower($email);
    }

    private function propriedadesRequisicao(Request $request, int $statusCode, ?Throwable $exception = null): array
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        return array_filter([
            'acao' => $this->inferirAcao($request),
            'metodo' => $request->method(),
            'path' => $request->path(),
            'rota' => $request->route()?->getName(),
            'status_code' => $statusCode,
            'ip' => $request->ip(),
            'empresa_id' => $request->input('empresa_id') ?: $user?->empresa_id,
            'usuario_id' => $user?->id,
            'usuario_nome' => $user?->name,
            'usuario_email' => $user?->email,
            'token_id' => $token?->id,
            'token_name' => $token?->name,
            'route_parameters' => $this->parametrosRotaSeguros($request),
            'exception' => $exception ? $exception::class : null,
            'mensagem' => $exception ? 'Falha ao processar requisição.' : null,
        ], fn ($valor) => $valor !== null && $valor !== '' && $valor !== []);
    }

    private function inferirAcao(Request $request): string
    {
        return match ($request->method()) {
            'POST' => 'criar',
            'PUT', 'PATCH' => 'editar',
            'DELETE' => 'deletar',
            default => 'executar',
        };
    }

    private function parametrosRotaSeguros(Request $request): array
    {
        $parametros = [];

        foreach ($request->route()?->parameters() ?? [] as $chave => $valor) {
            if ($valor instanceof Model) {
                $parametros[$chave] = $valor->getKey();

                continue;
            }

            if (is_scalar($valor) || $valor === null) {
                $parametros[$chave] = $valor;
            }
        }

        return $parametros;
    }

    private function registrarSeguro(
        string $logName,
        string $evento,
        ?Model $entidade,
        array $propriedades,
        Request $request,
        ?Model $causador,
    ): void {
        try {
            $this->auditoriaService->registrar($logName, $evento, $entidade, $propriedades, $request, $causador);
        } catch (Throwable) {
            return;
        }
    }
}
