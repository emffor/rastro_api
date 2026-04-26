<?php

namespace App\Http\Controllers;

use App\Models\Cargo;
use App\Models\Permissao;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CargoController extends Controller
{
    /**
     * Listar cargos da empresa.
     */
    public function index(Request $request): JsonResponse
    {
        $empresaId = $request->empresa_id;

        $cargos = Cargo::where('empresa_id', $empresaId)
            ->with('permissoes')
            ->orderBy('nome')
            ->get();

        return response()->json($cargos);
    }

    /**
     * Criar cargo.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
        ]);

        $empresaId = $request->empresa_id;

        // Verifica se existe cargo com mesmo nome (incluindo deletados)
        $cargoExistente = Cargo::withTrashed()
            ->where('empresa_id', $empresaId)
            ->where('nome', $validated['nome'])
            ->first();

        if ($cargoExistente) {
            // Se foi deletado, restaura e atualiza
            if ($cargoExistente->trashed()) {
                $cargoExistente->restore();
                $cargoExistente->update(['descricao' => $validated['descricao'] ?? $cargoExistente->descricao]);

                return response()->json([
                    'mensagem' => 'Cargo restaurado com sucesso.',
                    'dados' => $cargoExistente,
                ], 201);
            }

            // Se não foi deletado, retorna erro
            return response()->json([
                'mensagem' => 'Já existe um cargo com esse nome.',
                'errors' => ['nome' => ['Já existe um cargo com esse nome.']],
            ], 422);
        }

        $validated['empresa_id'] = $empresaId;
        $cargo = Cargo::create($validated);

        return response()->json([
            'mensagem' => 'Cargo criado com sucesso.',
            'dados' => $cargo,
        ], 201);
    }

    /**
     * Exibir cargo.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $cargo = Cargo::where('empresa_id', $request->empresa_id)
            ->with('permissoes')
            ->findOrFail($id);

        return response()->json($cargo);
    }

    /**
     * Atualizar cargo.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $cargo = Cargo::where('empresa_id', $request->empresa_id)->findOrFail($id);

        $validated = $request->validate([
            'nome' => 'sometimes|string|max:255',
            'descricao' => 'nullable|string',
        ]);

        $cargo->update($validated);

        return response()->json([
            'mensagem' => 'Cargo atualizado com sucesso.',
            'dados' => $cargo,
        ]);
    }

    /**
     * Excluir cargo.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $cargo = Cargo::where('empresa_id', $request->empresa_id)->findOrFail($id);

        if ($cargo->usuarios()->count() > 0) {
            return response()->json([
                'mensagem' => 'Não é possível excluir cargo com usuários vinculados.',
            ], 400);
        }

        $cargo->delete();

        return response()->json([
            'mensagem' => 'Cargo excluído com sucesso.',
        ]);
    }

    /**
     * Sincronizar permissões do cargo.
     */
    public function sincronizarPermissoes(Request $request, string $id): JsonResponse
    {
        $cargo = Cargo::where('empresa_id', $request->empresa_id)->findOrFail($id);

        $validated = $request->validate([
            'permissoes' => 'nullable|array',
            'permissoes.*' => 'uuid|exists:permissoes,id',
        ]);

        $cargo->permissoes()->sync($validated['permissoes'] ?? []);

        return response()->json([
            'mensagem' => 'Permissões atualizadas com sucesso.',
            'dados' => $cargo->load('permissoes'),
        ]);
    }

    /**
     * Listar todas as permissões do sistema.
     */
    public function listarPermissoes(): JsonResponse
    {
        $permissoes = Permissao::orderBy('grupo')->orderBy('nome')->get();

        return response()->json($permissoes);
    }
}

