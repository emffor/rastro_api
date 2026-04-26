<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Permissao;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Activitylog\Models\Activity;

class AdminMasterController extends Controller
{
    /**
     * Verifica se é MASTER.
     */
    private function verificarMaster(Request $request): ?JsonResponse
    {
        if (! $request->user()->isMaster()) {
            return response()->json(['mensagem' => 'Acesso restrito ao MASTER.'], 403);
        }

        return null;
    }

    /**
     * Dashboard com estatísticas gerais.
     */
    public function dashboard(Request $request): JsonResponse
    {
        if ($erro = $this->verificarMaster($request)) {
            return $erro;
        }

        $stats = [
            'empresas' => [
                'total' => Empresa::count(),
                'ativas' => Empresa::where('ativo', true)->count(),
                'inativas' => Empresa::where('ativo', false)->count(),
            ],
            'usuarios' => [
                'total' => User::whereNotNull('empresa_id')->count(),
                'ativos' => User::whereNotNull('empresa_id')->where('ativo', true)->count(),
                'admins' => User::where('is_admin', true)->count(),
            ],
            'sessoes_ativas' => PersonalAccessToken::whereHasMorph('tokenable', User::class, function ($query) {
                $query->whereNotNull('empresa_id');
            })
                ->select('tokenable_id')
                ->distinct()
                ->count('tokenable_id'),
        ];

        return response()->json($stats);
    }

    /**
     * Listar todas as empresas com estatísticas.
     */
    public function listarEmpresas(Request $request): JsonResponse
    {
        if ($erro = $this->verificarMaster($request)) {
            return $erro;
        }

        $empresas = Empresa::withCount('usuarios')
            ->withCount(['usuarios as usuarios_ativos_count' => function ($query) {
                $query->where('ativo', true);
            }])
            ->withCount(['usuarios as usuarios_logados_count' => function ($query) {
                $query->whereHas('tokens');
            }])
            ->with(['admin' => function ($query) {
                $query->select('id', 'empresa_id', 'email', 'name');
            }])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($empresa) {
                $empresa->email = $empresa->admin?->email ?? null;

                return $empresa;
            });

        return response()->json($empresas);
    }

    /**
     * Detalhes de uma empresa com todos os usuários.
     */
    public function detalheEmpresa(Request $request, string $id): JsonResponse
    {
        if ($erro = $this->verificarMaster($request)) {
            return $erro;
        }

        $empresa = Empresa::with(['usuarios.cargo', 'cargos.permissoes'])
            ->withCount('usuarios')
            ->findOrFail($id);

        // Contar sessões ativas da empresa
        $sessoesAtivas = PersonalAccessToken::whereHasMorph('tokenable', User::class, function ($query) use ($id) {
            $query->where('empresa_id', $id);
        })->count();

        return response()->json([
            'empresa' => $empresa,
            'sessoes_ativas' => $sessoesAtivas,
        ]);
    }

    /**
     * Ativar/Desativar empresa.
     */
    public function toggleEmpresa(Request $request, string $id): JsonResponse
    {
        if ($erro = $this->verificarMaster($request)) {
            return $erro;
        }

        $empresa = Empresa::findOrFail($id);
        $empresa->ativo = ! $empresa->ativo;
        $empresa->save();

        $status = $empresa->ativo ? 'ativada' : 'desativada';

        // Se desativou, revogar todos os tokens dos usuários
        if (! $empresa->ativo) {
            $this->revogarTokensEmpresa($id);
        }

        return response()->json([
            'mensagem' => "Empresa {$status} com sucesso.",
            'dados' => $empresa,
        ]);
    }

    /**
     * Forçar logout de todos os usuários de uma empresa.
     */
    public function forcarLogoutEmpresa(Request $request, string $id): JsonResponse
    {
        if ($erro = $this->verificarMaster($request)) {
            return $erro;
        }

        $count = $this->revogarTokensEmpresa($id);

        return response()->json([
            'mensagem' => "{$count} sessões encerradas com sucesso.",
        ]);
    }

    /**
     * Listar usuários de uma empresa.
     */
    public function usuariosEmpresa(Request $request, string $id): JsonResponse
    {
        if ($erro = $this->verificarMaster($request)) {
            return $erro;
        }

        $usuarios = User::where('empresa_id', $id)
            ->with('cargo')
            ->withCount('tokens')
            ->orderBy('name')
            ->get();

        return response()->json($usuarios);
    }

    public function listarUsuarios(Request $request): JsonResponse
    {
        if ($erro = $this->verificarMaster($request)) {
            return $erro;
        }

        $usuarios = User::query()
            ->whereNotNull('empresa_id')
            ->with('empresa', 'cargo')
            ->withCount('tokens')
            ->orderBy('name')
            ->get();

        return response()->json($usuarios);
    }

    /**
     * Ativar/Desativar usuário.
     */
    public function toggleUsuario(Request $request, string $id): JsonResponse
    {
        if ($erro = $this->verificarMaster($request)) {
            return $erro;
        }

        $usuario = User::findOrFail($id);
        $usuario->ativo = ! $usuario->ativo;
        $usuario->save();

        $status = $usuario->ativo ? 'ativado' : 'desativado';

        // Se desativou, revogar tokens
        if (! $usuario->ativo) {
            $usuario->tokens()->delete();
        }

        return response()->json([
            'mensagem' => "Usuário {$status} com sucesso.",
            'dados' => $usuario,
        ]);
    }

    /**
     * Forçar logout de um usuário específico.
     */
    public function forcarLogoutUsuario(Request $request, string $id): JsonResponse
    {
        if ($erro = $this->verificarMaster($request)) {
            return $erro;
        }

        $usuario = User::findOrFail($id);
        $count = $usuario->tokens()->delete();

        return response()->json([
            'mensagem' => "{$count} sessões do usuário encerradas.",
        ]);
    }

    /**
     * Listar todas as permissões do sistema.
     */
    public function listarPermissoes(Request $request): JsonResponse
    {
        if ($erro = $this->verificarMaster($request)) {
            return $erro;
        }

        $permissoes = Permissao::orderBy('grupo')->orderBy('nome')->get();

        return response()->json($permissoes);
    }

    public function logs(Request $request): JsonResponse
    {
        if ($erro = $this->verificarMaster($request)) {
            return $erro;
        }

        $perPage = max(1, min((int) $request->input('per_page', 20), 100));
        $busca = trim((string) $request->input('busca', ''));

        $logs = Activity::query()
            ->with('causer')
            ->when($busca !== '', function ($query) use ($busca) {
                $query->where(function ($subQuery) use ($busca) {
                    $subQuery->where('description', 'like', "%{$busca}%")
                        ->orWhere('log_name', 'like', "%{$busca}%")
                        ->orWhere('event', 'like', "%{$busca}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $usuarioEfetivoIds = collect($logs->items())
            ->map(fn (Activity $log) => data_get($log->properties, 'usuario_efetivo_id'))
            ->filter()
            ->values()
            ->all();

        $usuariosEfetivos = User::query()
            ->whereIn('id', $usuarioEfetivoIds)
            ->get()
            ->keyBy('id');

        $dados = collect($logs->items())->map(function (Activity $log) use ($usuariosEfetivos) {
            $usuarioEfetivoId = data_get($log->properties, 'usuario_efetivo_id');
            $usuarioEfetivo = $usuarioEfetivoId ? $usuariosEfetivos->get($usuarioEfetivoId) : null;

            return [
                'id' => $log->id,
                'log_name' => $log->log_name,
                'description' => $log->description,
                'event' => $log->event,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'properties' => $log->properties,
                'causer' => $log->causer ? [
                    'name' => $log->causer->name,
                    'email' => $log->causer->email,
                ] : null,
                'usuario_efetivo' => $usuarioEfetivo ? [
                    'id' => $usuarioEfetivo->id,
                    'name' => $usuarioEfetivo->name,
                    'email' => $usuarioEfetivo->email,
                ] : null,
                'created_at' => $log->created_at,
            ];
        })->values();

        return response()->json([
            'dados' => $dados,
            'paginacao' => [
                'pagina' => $logs->currentPage(),
                'itens_por_pagina' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Revoga todos os tokens de uma empresa.
     */
    private function revogarTokensEmpresa(string $empresaId): int
    {
        return PersonalAccessToken::whereHasMorph('tokenable', User::class, function ($query) use ($empresaId) {
            $query->where('empresa_id', $empresaId);
        })->delete();
    }
}
