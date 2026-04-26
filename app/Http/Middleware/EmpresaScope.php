<?php

namespace App\Http\Middleware;

use App\Services\AdminMasterContextService;
use App\Models\Empresa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmpresaScope
{
    public function __construct(
        private readonly AdminMasterContextService $adminMasterContextService,
    ) {}

    /**
     * Injeta empresa_id nas requisições para isolamento de dados.
     * Também verifica se usuário/empresa estão ativos.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Verifica se usuário está ativo
        if (!$user->ativo) {
            $user->currentAccessToken()->delete();
            return response()->json([
                'mensagem' => 'Usuário inativo.',
            ], 403);
        }

        if ($user->isMaster()) {
            $empresaId = $this->adminMasterContextService->empresaControladaId($user);

            if (!$empresaId) {
                return response()->json([
                    'mensagem' => 'Selecione uma empresa para acessar módulos operacionais.',
                ], 403);
            }

            $empresa = Empresa::query()->find($empresaId);

            if (!$empresa || !$empresa->ativo) {
                $user->currentAccessToken()->delete();
                return response()->json([
                    'mensagem' => 'Empresa controlada inválida ou inativa.',
                ], 403);
            }

            try {
                $this->adminMasterContextService->adminEfetivoDaEmpresa($empresaId);
            } catch (\Throwable) {
                return response()->json([
                    'mensagem' => 'A empresa selecionada não possui administrador ativo.',
                ], 403);
            }

            $request->merge(['empresa_id' => $empresaId]);
            return $next($request);
        }

        // Verifica se a empresa está ativa
        if ($user->empresa && !$user->empresa->ativo) {
            $user->currentAccessToken()->delete();
            return response()->json([
                'mensagem' => 'Empresa inativa. Entre em contato com o suporte.',
            ], 403);
        }

        // Usuários normais usam sua própria empresa
        $request->merge(['empresa_id' => $user->empresa_id]);

        return $next($request);
    }
}
