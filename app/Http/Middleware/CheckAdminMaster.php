<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminMaster
{
    /**
     * Verifica se o usuário é Master.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'mensagem' => 'Não autenticado.',
            ], 401);
        }

        if (!$user->is_master) {
            return response()->json([
                'mensagem' => 'Acesso restrito ao administrador master.',
            ], 403);
        }

        return $next($request);
    }
}
