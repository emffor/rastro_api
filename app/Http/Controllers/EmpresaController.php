<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EmpresaController extends Controller
{
    /**
     * Listar empresas (apenas MASTER via middleware).
     */
    public function index(Request $request): JsonResponse
    {
        $empresas = Empresa::orderBy('nome')->get();
        return response()->json($empresas);
    }

    /**
     * Exibir uma empresa.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        // Master pode ver qualquer empresa, Admin só a própria
        if (!$user->isMaster() && $user->empresa_id !== $id) {
            return response()->json(['mensagem' => 'Acesso negado.'], 403);
        }

        $empresa = Empresa::with('admin')->findOrFail($id);
        return response()->json($empresa);
    }

    /**
     * Atualizar empresa.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        // Master pode editar qualquer empresa, Admin só a própria
        if (!$user->isMaster() && $user->empresa_id !== $id) {
            return response()->json(['mensagem' => 'Acesso negado.'], 403);
        }

        $empresa = Empresa::findOrFail($id);

        $validated = $request->validate([
            'nome' => 'sometimes|string|max:255',
            'email' => 'nullable|email',
            'telefone' => 'nullable|string|max:20',
            'ativo' => 'sometimes|boolean',
            'admin_senha' => 'nullable|string|min:6',
        ]);

        DB::transaction(function () use ($empresa, $validated) {
            $dadosEmpresa = $validated;
            unset($dadosEmpresa['admin_senha']);

            $empresa->update($dadosEmpresa);

            if (!empty($validated['admin_senha'])) {
                $admin = $empresa->admin;

                if (!$admin) {
                    throw ValidationException::withMessages([
                        'admin_senha' => ['Administrador da empresa não encontrado.'],
                    ]);
                }

                $admin->password = Hash::make($validated['admin_senha']);
                $admin->save();
            }
        });

        return response()->json([
            'mensagem' => 'Empresa atualizada com sucesso.',
            'dados' => $empresa,
        ]);
    }

    /**
     * Desativar empresa (soft delete) - apenas MASTER via middleware.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $empresa = Empresa::findOrFail($id);
        $empresa->delete();

        return response()->json([
            'mensagem' => 'Empresa excluída com sucesso.',
        ]);
    }
}
