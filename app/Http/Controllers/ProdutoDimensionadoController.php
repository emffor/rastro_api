<?php

namespace App\Http\Controllers;

use App\Services\ProdutoDimensionadoService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProdutoDimensionadoController extends Controller
{
    public function __construct(
        private readonly ProdutoDimensionadoService $produtoDimensionadoService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $filtros = $request->only(['busca', 'especie_id', 'tipo_dof', 'ativo', 'all', 'with']);
            $perPage = (int) $request->input('per_page', 15);

            $produtos = $this->produtoDimensionadoService->listar($filtros, $perPage);

            return response()->json([
                'dados' => $produtos->items(),
                'paginacao' => [
                    'pagina' => $produtos->currentPage(),
                    'itens_por_pagina' => $produtos->perPage(),
                    'total' => $produtos->total(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['mensagem' => 'ERRO_LISTAR_PRODUTOS_DIMENSIONADOS', 'erro' => $e->getMessage()], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $produto = $this->produtoDimensionadoService->buscarPorId($id);
            return response()->json(['dados' => $produto]);
        } catch (\Throwable) {
            return response()->json(['mensagem' => 'PRODUTO_DIMENSIONADO_NAO_ENCONTRADO'], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nome' => 'nullable|string|max:160',
            'especie_id' => 'nullable|uuid|exists:especies,id',
            'tipo_especie' => 'required_without:especie_id|string|max:50',
            'nome_popular' => 'required_without:especie_id|string|max:255',
            'tipo_dof' => 'nullable|string|max:50',
            'espessura_cm' => 'required|numeric|min:0.01',
            'largura_cm' => 'required|numeric|min:0.01',
            'comprimento_m' => 'required|numeric|min:0.01',
            'observacao' => 'nullable|string|max:255',
            'ativo' => 'nullable|boolean',
        ]);

        try {
            $produto = $this->produtoDimensionadoService->criar($validated);

            return response()->json([
                'mensagem' => 'PRODUTO_DIMENSIONADO_CRIADO_SUCESSO',
                'dados' => $produto,
            ], 201);
        } catch (\DomainException $e) {
            return response()->json(['mensagem' => $e->getMessage()], 422);
        } catch (QueryException $e) {
            return response()->json(['mensagem' => 'Já existe produto dimensionado ativo com a mesma combinação de tipo, dimensões e grupo de espécies vinculadas.'], 422);
        } catch (\Throwable $e) {
            return response()->json(['mensagem' => 'ERRO_CRIAR_PRODUTO_DIMENSIONADO', 'erro' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'nome' => 'sometimes|nullable|string|max:160',
            'especie_id' => 'sometimes|uuid|exists:especies,id',
            'tipo_especie' => 'sometimes|string|max:50',
            'nome_popular' => 'sometimes|string|max:255',
            'tipo_dof' => 'nullable|string|max:50',
            'espessura_cm' => 'sometimes|numeric|min:0.01',
            'largura_cm' => 'sometimes|numeric|min:0.01',
            'comprimento_m' => 'sometimes|numeric|min:0.01',
            'observacao' => 'nullable|string|max:255',
            'ativo' => 'sometimes|boolean',
        ]);

        try {
            $produto = $this->produtoDimensionadoService->atualizar($id, $validated);

            return response()->json([
                'mensagem' => 'PRODUTO_DIMENSIONADO_ATUALIZADO_SUCESSO',
                'dados' => $produto,
            ]);
        } catch (\DomainException $e) {
            return response()->json(['mensagem' => $e->getMessage()], 422);
        } catch (QueryException) {
            return response()->json(['mensagem' => 'Já existe produto dimensionado ativo com a mesma combinação de tipo, dimensões e grupo de espécies vinculadas.'], 422);
        } catch (\Throwable $e) {
            return response()->json(['mensagem' => 'ERRO_ATUALIZAR_PRODUTO_DIMENSIONADO', 'erro' => $e->getMessage()], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->produtoDimensionadoService->excluir($id);
            return response()->json(['mensagem' => 'PRODUTO_DIMENSIONADO_EXCLUIDO_SUCESSO']);
        } catch (\DomainException $e) {
            return response()->json(['mensagem' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['mensagem' => 'ERRO_EXCLUIR_PRODUTO_DIMENSIONADO', 'erro' => $e->getMessage()], 500);
        }
    }
}
