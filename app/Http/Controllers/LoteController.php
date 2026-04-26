<?php

namespace App\Http\Controllers;

use App\Services\LoteService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Exception;

class LoteController extends Controller
{
    protected LoteService $service;

    public function __construct(LoteService $service)
    {
        $this->service = $service;
    }

    public function todos(): JsonResponse
    {
        try {
            $lotes = $this->service->listarTodos();

            return response()->json([
                'mensagem' => 'LOTES_LISTADOS',
                'dados' => $lotes,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'mensagem' => 'ERRO_AO_LISTAR_LOTES',
                'erro' => $e->getMessage(),
            ], 400);
        }
    }

    public function index(string $patioId): JsonResponse
    {
        try {
            $lotes = $this->service->listarPorPatio($patioId);

            return response()->json([
                'mensagem' => 'LOTES_LISTADOS',
                'dados' => $lotes,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'mensagem' => 'ERRO_AO_LISTAR_LOTES',
                'erro' => $e->getMessage(),
            ], 400);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $lote = $this->service->buscar($id);

            return response()->json([
                'mensagem' => 'LOTE_ENCONTRADO',
                'dados' => $lote,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'mensagem' => 'LOTE_NAO_ENCONTRADO',
                'erro' => $e->getMessage(),
            ], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patio_id' => 'required|uuid|exists:patios,id',
            'codigo' => 'nullable|string|max:50',
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'pos_x' => 'nullable|numeric',
            'pos_y' => 'nullable|numeric',
            'largura' => 'nullable|numeric|min:10',
            'altura' => 'nullable|numeric|min:10',
            'rotacao' => 'nullable|numeric|min:0|max:360',
            'cor' => 'nullable|string|max:20',
            'cor_borda' => 'nullable|string|max:20',
            'status' => 'nullable|in:DISPONIVEL,OCUPADO,RESERVADO,BLOQUEADO',
            'capacidade_volume' => 'nullable|numeric|min:0',
            'largura_metros' => 'nullable|numeric|min:0.1',
            'comprimento_metros' => 'nullable|numeric|min:0.1',
            'altura_metros' => 'nullable|numeric|min:0.1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'mensagem' => 'DADOS_INVALIDOS',
                'erros' => $validator->errors(),
            ], 422);
        }

        try {
            $lote = $this->service->criar($validator->validated());

            return response()->json([
                'mensagem' => 'LOTE_CRIADO',
                'dados' => $lote,
            ], 201);
        } catch (Exception $e) {
            if (str_starts_with($e->getMessage(), 'COLISAO_LAYOUT_PATIO::')) {
                return response()->json([
                    'mensagem' => 'COLISAO_LAYOUT_PATIO',
                    'erro' => str_replace('COLISAO_LAYOUT_PATIO::', '', $e->getMessage()),
                ], 422);
            }
            return response()->json([
                'mensagem' => 'ERRO_AO_CRIAR_LOTE',
                'erro' => $e->getMessage(),
            ], 400);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'codigo' => 'sometimes|nullable|string|max:50',
            'nome' => 'sometimes|string|max:255',
            'descricao' => 'nullable|string',
            'pos_x' => 'nullable|numeric',
            'pos_y' => 'nullable|numeric',
            'largura' => 'nullable|numeric|min:10',
            'altura' => 'nullable|numeric|min:10',
            'rotacao' => 'nullable|numeric|min:0|max:360',
            'cor' => 'nullable|string|max:20',
            'cor_borda' => 'nullable|string|max:20',
            'status' => 'nullable|in:DISPONIVEL,OCUPADO,RESERVADO,BLOQUEADO',
            'capacidade_volume' => 'nullable|numeric|min:0',
            'largura_metros' => 'nullable|numeric|min:0.1',
            'comprimento_metros' => 'nullable|numeric|min:0.1',
            'altura_metros' => 'nullable|numeric|min:0.1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'mensagem' => 'DADOS_INVALIDOS',
                'erros' => $validator->errors(),
            ], 422);
        }

        try {
            $lote = $this->service->atualizar($id, $validator->validated());

            return response()->json([
                'mensagem' => 'LOTE_ATUALIZADO',
                'dados' => $lote,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'mensagem' => 'ERRO_AO_ATUALIZAR_LOTE',
                'erro' => $e->getMessage(),
            ], 400);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->service->excluir($id);

            return response()->json([
                'mensagem' => 'LOTE_EXCLUIDO',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'mensagem' => 'ERRO_AO_EXCLUIR_LOTE',
                'erro' => $e->getMessage(),
            ], 400);
        }
    }

    public function atualizarPosicoes(Request $request, string $patioId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lotes' => 'required|array',
            'lotes.*.id' => 'required|uuid',
            'lotes.*.pos_x' => 'nullable|numeric',
            'lotes.*.pos_y' => 'nullable|numeric',
            'lotes.*.largura' => 'nullable|numeric',
            'lotes.*.altura' => 'nullable|numeric',
            'lotes.*.rotacao' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'mensagem' => 'DADOS_INVALIDOS',
                'erros' => $validator->errors(),
            ], 422);
        }

        try {
            $lotes = $this->service->atualizarPosicoes($patioId, $request->input('lotes'));

            return response()->json([
                'mensagem' => 'POSICOES_ATUALIZADAS',
                'dados' => $lotes,
            ]);
        } catch (Exception $e) {
            if (str_starts_with($e->getMessage(), 'COLISAO_LAYOUT_PATIO::')) {
                return response()->json([
                    'mensagem' => 'COLISAO_LAYOUT_PATIO',
                    'erro' => str_replace('COLISAO_LAYOUT_PATIO::', '', $e->getMessage()),
                ], 422);
            }
            return response()->json([
                'mensagem' => 'ERRO_AO_ATUALIZAR_POSICOES',
                'erro' => $e->getMessage(),
            ], 400);
        }
    }

}
