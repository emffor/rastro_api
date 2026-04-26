<?php
namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class ResponseHelper
{
    /**
     * Retorna uma resposta de sucesso padronizada.
     * Se usar paginação, passe um array com as chaves: pagina, itens_por_pagina, total.
     */
    public static function successResponse(string $mensagem, mixed $dados = [], array $paginacao = [], int $status = 200): JsonResponse
    {
        $response = [
            'mensagem' => mb_strtoupper($mensagem),
            'dados' => $dados,
        ];

        if (!empty($paginacao)) {
            $response['paginacao'] = $paginacao;
        }

        return response()->json($response, $status);
    }

    /**
     * Retorna uma resposta de erro padronizada.
     */
    public static function errorResponse(string $mensagem, mixed $erro = null, int $status = 400): JsonResponse
    {
        $response = [
            'mensagem' => mb_strtoupper($mensagem),
        ];

        if ($erro !== null) {
            $response['erro'] = $erro;
        }

        return response()->json($response, $status);
    }
}
