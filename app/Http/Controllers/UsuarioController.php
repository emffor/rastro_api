<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    /**
     * Listar usuários da empresa.
     */
    public function index(Request $request): JsonResponse
    {
        $empresaId = $request->empresa_id;

        $usuarios = User::where('empresa_id', $empresaId)
            ->with('cargo')
            ->orderBy('name')
            ->get();

        return response()->json($usuarios);
    }

    /**
     * Criar usuário.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->where(fn ($query) => $query->where('empresa_id', $request->empresa_id)),
            ],
            'password' => 'required|string|min:6',
            'cargo_id' => 'required|uuid|exists:cargos,id',
        ]);

        $usuario = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'empresa_id' => $request->empresa_id,
            'cargo_id' => $validated['cargo_id'],
            'is_admin' => false,
        ]);

        return response()->json([
            'mensagem' => 'Usuário criado com sucesso.',
            'dados' => $usuario->load('cargo'),
        ], 201);
    }

    /**
     * Exibir usuário.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $usuario = User::where('empresa_id', $request->empresa_id)
            ->with('cargo')
            ->findOrFail($id);

        return response()->json($usuario);
    }

    /**
     * Atualizar usuário.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $usuario = User::where('empresa_id', $request->empresa_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')
                    ->ignore($usuario->id)
                    ->where(fn ($query) => $query->where('empresa_id', $request->empresa_id)),
            ],
            'password' => 'nullable|string|min:6',
            'cargo_id' => 'sometimes|uuid|exists:cargos,id',
            'ativo' => 'sometimes|boolean',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $usuario->update($validated);

        return response()->json([
            'mensagem' => 'Usuário atualizado com sucesso.',
            'dados' => $usuario->load('cargo'),
        ]);
    }

    /**
     * Ativar/Desativar usuário.
     */
    public function toggleAtivo(Request $request, string $id): JsonResponse
    {
        $usuario = User::where('empresa_id', $request->empresa_id)->findOrFail($id);

        // Não permite desativar o próprio usuário
        if ($usuario->id === $request->user()->id) {
            return response()->json([
                'mensagem' => 'Você não pode desativar sua própria conta.',
            ], 400);
        }

        // Não permite desativar admins
        if ($usuario->is_admin) {
            return response()->json([
                'mensagem' => 'Não é possível desativar o admin da empresa.',
            ], 400);
        }

        // Toggle o status ativo
        $novoStatus = !$usuario->ativo;
        $usuario->ativo = $novoStatus;
        $usuario->save();

        // Se desativou, invalida todos os tokens do usuário
        if (!$novoStatus) {
            $usuario->tokens()->delete();
        }

        return response()->json([
            'mensagem' => $novoStatus ? 'Usuário ativado com sucesso.' : 'Usuário desativado com sucesso.',
            'dados' => $usuario->load('cargo'),
        ]);
    }

    /**
     * Excluir usuário.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $usuario = User::where('empresa_id', $request->empresa_id)->findOrFail($id);

        if ($usuario->is_admin) {
            return response()->json([
                'mensagem' => 'Não é possível excluir o admin da empresa.',
            ], 400);
        }

        // Não permite excluir o próprio usuário
        if ($usuario->id === $request->user()->id) {
            return response()->json([
                'mensagem' => 'Você não pode excluir sua própria conta.',
            ], 400);
        }

        // Invalida todos os tokens do usuário antes de excluir
        $usuario->tokens()->delete();

        $usuario->delete();

        return response()->json([
            'mensagem' => 'Usuário excluído com sucesso.',
        ]);
    }
}
