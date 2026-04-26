<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermissao
{
    /**
     * Verifica se o usuário tem a permissão necessária.
     */
    public function handle(Request $request, Closure $next, string $permissao): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'mensagem' => 'Não autenticado.',
            ], 401);
        }

        if (!$user->temPermissao($permissao)) {
            return response()->json([
                'mensagem' => 'Sem permissão para esta ação.',
            ], 403);
        }

        return $next($request);
    }
}
