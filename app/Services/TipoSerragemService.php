<?php

namespace App\Services;

use App\Models\TipoSerragem;
use App\Support\ProdutoDimensionadoEspecieMatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TipoSerragemService
{
    public function listar(): Collection
    {
        return TipoSerragem::query()
            ->orderBy('nome')
            ->get();
    }

    public function criar(array $dados): TipoSerragem
    {
        return $this->obterOuCriarPorNome((string) $dados['nome']);
    }

    public function obterOuCriarPorNome(string $nome, ?string $empresaId = null): TipoSerragem
    {
        $nomeNormalizado = ProdutoDimensionadoEspecieMatcher::normalizarTipo($nome);
        if ($nomeNormalizado === '') {
            throw new \DomainException('Tipo de serragem é obrigatório.');
        }

        $empresaId = $empresaId ?: $this->resolverEmpresaId();
        if ($empresaId === '') {
            throw new \DomainException('Empresa inválida para o tipo de serragem.');
        }

        return DB::transaction(function () use ($empresaId, $nomeNormalizado): TipoSerragem {
            $tipo = TipoSerragem::withTrashed()
                ->where('empresa_id', $empresaId)
                ->where('nome', $nomeNormalizado)
                ->first();

            if ($tipo) {
                if (method_exists($tipo, 'trashed') && $tipo->trashed()) {
                    $tipo->restore();
                }

                return $tipo;
            }

            return TipoSerragem::create([
                'empresa_id' => $empresaId,
                'nome' => $nomeNormalizado,
            ]);
        });
    }

    public function obterPorId(string $id): TipoSerragem
    {
        return TipoSerragem::query()->findOrFail($id);
    }

    public function resolverNome(?string $tipoSerragemId, ?string $nomeFallback = null): string
    {
        if ($tipoSerragemId) {
            $tipo = TipoSerragem::query()->find($tipoSerragemId);
            if ($tipo) {
                return $tipo->nome;
            }
        }

        return ProdutoDimensionadoEspecieMatcher::normalizarTipo($nomeFallback);
    }

    public function criarPadroesParaEmpresa(string $empresaId): int
    {
        $total = 0;

        foreach (TipoSerragem::TIPOS_PADRAO as $nome) {
            $tipo = $this->obterOuCriarPorNome($nome, $empresaId);
            if ($tipo->wasRecentlyCreated) {
                $total++;
            }
        }

        return $total;
    }

    private function resolverEmpresaId(): string
    {
        return (string) (request()->get('empresa_id') ?: auth()->user()?->empresa_id ?: '');
    }
}
