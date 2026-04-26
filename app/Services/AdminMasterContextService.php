<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminMasterContextService
{
    private const TOKEN_PREFIXO_CONTROLE_EMPRESA = 'controlar_empresa:';
    private const TOKEN_NOME_PADRAO = 'auth-token';

    public function empresaControladaId(?User $user = null): ?string
    {
        $user ??= request()->user();

        if (!$user?->isMaster()) {
            return null;
        }

        $tokenName = (string) ($user->currentAccessToken()?->name ?? '');

        if (str_starts_with($tokenName, self::TOKEN_PREFIXO_CONTROLE_EMPRESA)) {
            return str_replace(self::TOKEN_PREFIXO_CONTROLE_EMPRESA, '', $tokenName);
        }

        if (str_starts_with($tokenName, 'empresa:')) {
            return str_replace('empresa:', '', $tokenName);
        }

        return null;
    }

    public function estaControlandoEmpresa(?User $user = null): bool
    {
        return $this->empresaControladaId($user) !== null;
    }

    public function empresaControlada(?User $user = null): ?Empresa
    {
        $empresaId = $this->empresaControladaId($user);

        if (!$empresaId) {
            return null;
        }

        return Empresa::query()->find($empresaId);
    }

    public function adminEfetivoDaEmpresa(string $empresaId): User
    {
        $admin = User::query()
            ->where('empresa_id', $empresaId)
            ->where('is_admin', true)
            ->where('ativo', true)
            ->orderBy('created_at')
            ->first();

        if (!$admin) {
            throw ValidationException::withMessages([
                'empresa_id' => ['A empresa selecionada não possui administrador ativo.'],
            ]);
        }

        return $admin;
    }

    public function usuarioEfetivo(?Request $request = null): ?User
    {
        $request ??= request();
        $user = $request->user();

        if (!$user) {
            return null;
        }

        if (!$user->isMaster()) {
            return $user;
        }

        $empresaId = $this->empresaControladaId($user);

        if (!$empresaId) {
            return $user;
        }

        return $this->adminEfetivoDaEmpresa($empresaId);
    }

    public function usuarioEfetivoId(?Request $request = null): ?string
    {
        return $this->usuarioEfetivo($request)?->getKey();
    }

    public function iniciarControle(User $user, string $empresaId): array
    {
        if (!$user->isMaster()) {
            throw ValidationException::withMessages([
                'empresa_id' => ['Apenas o administrador master pode controlar empresas.'],
            ]);
        }

        $empresa = Empresa::query()->findOrFail($empresaId);

        if (!$empresa->ativo) {
            throw ValidationException::withMessages([
                'empresa_id' => ['Não é possível controlar uma empresa inativa.'],
            ]);
        }

        $usuarioEfetivo = $this->adminEfetivoDaEmpresa((string) $empresa->id);

        $user->currentAccessToken()?->update([
            'name' => self::TOKEN_PREFIXO_CONTROLE_EMPRESA . $empresa->id,
        ]);

        return [
            'modo' => 'controlando_empresa',
            'empresa_controlada' => $empresa,
            'usuario_efetivo' => $usuarioEfetivo,
        ];
    }

    public function encerrarControle(User $user): void
    {
        if ($user->isMaster()) {
            $user->currentAccessToken()?->update(['name' => self::TOKEN_NOME_PADRAO]);
        }
    }

    public function contexto(User $user): array
    {
        $empresa = $this->empresaControlada($user);
        $usuarioEfetivo = $empresa
            ? $this->adminEfetivoDaEmpresa((string) $empresa->id)
            : $user;

        return [
            'modo' => $empresa ? 'controlando_empresa' : 'global',
            'empresa_controlada' => $empresa,
            'usuario_efetivo' => $usuarioEfetivo,
        ];
    }

    public function propriedadesAuditoria(?Request $request = null): array
    {
        $request ??= request();
        $user = $request->user();

        if (!$user?->isMaster() || !$this->estaControlandoEmpresa($user)) {
            return [];
        }

        return [
            'executado_por_admin_master' => true,
            'admin_master_id' => $user->getKey(),
            'admin_master_nome' => $user->name,
            'empresa_controlada_id' => $this->empresaControladaId($user),
            'usuario_efetivo_id' => $this->usuarioEfetivoId($request),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }
}
