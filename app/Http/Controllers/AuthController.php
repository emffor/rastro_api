<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Empresa;
use App\Models\User;
use App\Rules\CnpjValido;
use App\Services\AdminMasterContextService;
use App\Services\AuditoriaOperacionalService;
use App\Services\EmpresaProvisionamentoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly EmpresaProvisionamentoService $empresaProvisionamentoService,
        private readonly AdminMasterContextService $adminMasterContextService,
        private readonly AuditoriaOperacionalService $auditoriaOperacionalService,
    ) {}

    /**
     * Login do usuário.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'empresa_id' => 'nullable|uuid|exists:empresas,id',
        ]);

        $usuarios = User::with('empresa')
            ->where('email', $request->email)
            ->when($request->filled('empresa_id'), fn ($query) => $query->where('empresa_id', $request->empresa_id))
            ->get();

        $user = $usuarios->first(fn (User $candidate) => Hash::check($request->password, $candidate->password));

        if (! $user) {
            $this->auditoriaOperacionalService->registrarLoginFalhou($request, 'credenciais_invalidas');

            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        if (! $user->ativo) {
            $this->auditoriaOperacionalService->registrarLoginFalhou($request, 'usuario_inativo', $user);

            throw ValidationException::withMessages([
                'email' => ['Usuário inativo.'],
            ]);
        }

        // Verifica se a empresa está ativa (ignora para MASTER)
        if (! $user->is_master && $user->empresa && ! $user->empresa->ativo) {
            $this->auditoriaOperacionalService->registrarLoginFalhou($request, 'empresa_inativa', $user);

            throw ValidationException::withMessages([
                'email' => ['Empresa inativa. Entre em contato com o suporte.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        // Carrega as relações necessárias
        $user->load('empresa', 'cargo.permissoes');
        $this->auditoriaOperacionalService->registrarLoginRealizado($user, $request);

        return response()->json([
            'mensagem' => 'Login realizado com sucesso.',
            'dados' => [
                'usuario' => $user,
                'token' => $token,
                'permissoes' => $user->permissoes()->pluck('nome'),
            ],
        ]);
    }

    /**
     * Logout do usuário.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->auditoriaOperacionalService->registrarLogoutRealizado($user, $request);
        $user->currentAccessToken()->delete();

        return response()->json([
            'mensagem' => 'Logout realizado com sucesso.',
        ]);
    }

    /**
     * Dados do usuário logado.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('empresa', 'cargo.permissoes');

        return response()->json([
            'dados' => $user,
            'permissoes' => $user->permissoes()->pluck('nome'),
            'contexto' => $this->formatarContexto($user),
        ]);
    }

    public function contexto(Request $request): JsonResponse
    {
        return ResponseHelper::successResponse(
            'CONTEXTO_AUTENTICACAO',
            $this->formatarContexto($request->user()),
        );
    }

    /**
     * Registro de nova empresa com admin.
     * Apenas MASTER pode criar empresas.
     */
    public function registrarEmpresa(Request $request): JsonResponse
    {
        $request->merge([
            'tipo_empresa' => strtoupper(trim((string) $request->input('tipo_empresa'))),
        ]);

        $request->validate([
            'empresa_nome' => 'required|string|max:255',
            'empresa_cnpj' => ['required', 'string', 'max:18', 'unique:empresas,cnpj', new CnpjValido],
            'tipo_empresa' => ['required', 'string', Rule::in(Empresa::TIPOS)],
            'admin_nome' => 'required|string|max:255',
            'admin_email' => 'required|email',
            'admin_senha' => 'required|string|min:6',
        ]);

        $dados = DB::transaction(function () use ($request) {
            // Criar empresa
            $empresa = Empresa::create([
                'nome' => $request->empresa_nome,
                'cnpj' => $request->empresa_cnpj,
                'tipo_empresa' => $request->tipo_empresa,
            ]);

            // Criar usuário admin da empresa
            $admin = User::create([
                'name' => $request->admin_nome,
                'email' => $request->admin_email,
                'password' => Hash::make($request->admin_senha),
                'empresa_id' => $empresa->id,
                'is_admin' => true,
            ]);

            $provisionamento = $this->empresaProvisionamentoService->provisionarDadosIniciais($empresa);

            return [
                'empresa' => $empresa,
                'admin' => $admin,
                'provisionamento' => $provisionamento,
            ];
        });

        return response()->json([
            'mensagem' => 'Empresa criada com sucesso.',
            'dados' => $dados,
        ], 201);
    }

    /**
     * MASTER troca de empresa ativa.
     */
    public function trocarEmpresa(Request $request): JsonResponse
    {
        return $this->controlarEmpresa($request);
    }

    /**
     * MASTER inicia controle de uma empresa ativa.
     */
    public function controlarEmpresa(Request $request): JsonResponse
    {
        $request->validate([
            'empresa_id' => 'required|uuid|exists:empresas,id',
        ]);

        $user = $request->user();

        if (! $user->isMaster()) {
            return ResponseHelper::errorResponse('Apenas MASTER pode controlar empresas.', null, 403);
        }

        $contexto = $this->adminMasterContextService->iniciarControle($user, (string) $request->empresa_id);
        $this->auditoriaOperacionalService->registrarSessaoEmpresa(
            $request,
            'controle_empresa_iniciado',
            (string) $request->empresa_id,
        );

        return ResponseHelper::successResponse(
            'EMPRESA_CONTROLADA_COM_SUCESSO',
            $this->formatarContexto($user, $contexto),
        );
    }

    public function encerrarControleEmpresa(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isMaster()) {
            return ResponseHelper::errorResponse('Apenas MASTER pode encerrar controle de empresas.', null, 403);
        }

        $empresaId = $this->adminMasterContextService->empresaControladaId($user);

        $this->auditoriaOperacionalService->registrarSessaoEmpresa(
            $request,
            'controle_empresa_encerrado',
            $empresaId,
        );
        $this->adminMasterContextService->encerrarControle($user);

        return ResponseHelper::successResponse(
            'CONTROLE_EMPRESA_ENCERRADO',
            $this->formatarContexto($user),
        );
    }

    private function formatarContexto(User $user, ?array $contexto = null): array
    {
        $contexto ??= $this->adminMasterContextService->contexto($user);
        $empresa = $contexto['empresa_controlada'] ?? null;
        $usuarioEfetivo = $contexto['usuario_efetivo'] ?? null;

        return [
            'modo' => $contexto['modo'],
            'empresa_controlada' => $empresa ? [
                'id' => (string) $empresa->id,
                'nome' => $empresa->nome,
            ] : null,
            'usuario_efetivo' => $usuarioEfetivo ? [
                'id' => (string) $usuarioEfetivo->id,
                'nome' => $usuarioEfetivo->name,
            ] : null,
            'permissoes' => $usuarioEfetivo?->permissoes()->pluck('nome') ?? collect(),
        ];
    }
}
