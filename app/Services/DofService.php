<?php

namespace App\Services;

use App\Models\Dof;
use App\Models\DofItem;
use App\Models\Especie;
use App\Models\Movimentacao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;

class DofService
{
    private function aplicarFiltros(Builder $query, array $filtros = []): Builder
    {
        if (!empty($filtros['numero'])) {
            $query->where('numero', 'LIKE', "%{$filtros['numero']}%");
        }

        if (!empty($filtros['busca'])) {
            $busca = trim((string) $filtros['busca']);
            if ($busca !== '') {
                $query->where(function (Builder $subQuery) use ($busca) {
                    $subQuery->where('numero', 'LIKE', "%{$busca}%")
                        ->orWhere('origem', 'LIKE', "%{$busca}%")
                        ->orWhere('destino', 'LIKE', "%{$busca}%");
                });
            }
        }

        if (!empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        if (!empty($filtros['data_inicio'])) {
            $query->whereDate('data_emissao', '>=', $filtros['data_inicio']);
        }

        if (!empty($filtros['data_fim'])) {
            $query->whereDate('data_emissao', '<=', $filtros['data_fim']);
        }

        return $query;
    }

    private function resolverTipoItem(array $item): string
    {
        return trim((string) ($item['tipo'] ?? ''));
    }

    private function resolverTiposEspeciesPorItem(array $itens = []): array
    {
        $especiesIds = collect($itens)
            ->pluck('especie_id')
            ->filter(fn ($id) => filled($id))
            ->unique()
            ->values();

        if ($especiesIds->isEmpty()) {
            return [];
        }

        return Especie::query()
            ->with('tipoSerragem:id,nome')
            ->whereIn('id', $especiesIds)
            ->get(['id', 'tipo', 'nome_tipo', 'tipo_serragem_id'])
            ->mapWithKeys(fn (Especie $especie) => [
                (string) $especie->id => $especie->resolverTipoSerragemNome(),
            ])
            ->all();
    }

    private function resolverTipoItemPorEspecie(array $item, array $tiposEspecies): string
    {
        $especieId = (string) ($item['especie_id'] ?? '');
        $tipoDaEspecie = trim((string) ($tiposEspecies[$especieId] ?? ''));

        if ($tipoDaEspecie !== '') {
            return $tipoDaEspecie;
        }

        return $this->resolverTipoItem($item);
    }

    private function calcularVolumeTotalItens(array $itens = []): float
    {
        if (empty($itens)) {
            return 0.0;
        }

        return (float) collect($itens)->sum(function ($item) {
            return (float) ($item['quantidade_autorizada'] ?? 0);
        });
    }

    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Dof::with('itens.especie.tipoSerragem')
            ->orderBy('created_at', 'desc');

        $this->aplicarFiltros($query, $filtros);

        if (isset($filtros['all']) && $filtros['all'] === 'true') {
            return new LengthAwarePaginator(
                $query->get(),
                $query->count(),
                $query->count() ?: 1,
                1
            );
        }

        return $query->paginate($perPage);
    }

    public function listarParaRelatorio(array $filtros = []): Collection
    {
        $query = Dof::query()->orderBy('created_at', 'desc');
        $this->aplicarFiltros($query, $filtros);
        return $query->get();
    }

    public function buscarPorId(string $id): Dof
    {
        return Dof::with(['itens.especie.tipoSerragem', 'dofLotes.dofItem.especie.tipoSerragem', 'dofLotes.lote.patio', 'movimentacoes.usuario'])
            ->findOrFail($id);
    }

    public function criar(array $dados): Dof
    {
        try {
            return DB::transaction(function () use ($dados) {
                $numeroSerie = (string) ($dados['numero'] ?? $dados['serie'] ?? '');
                $tiposEspecies = $this->resolverTiposEspeciesPorItem($dados['itens'] ?? []);

                $volumeTotalItens = $this->calcularVolumeTotalItens($dados['itens'] ?? []);
                $volumeTotal = $volumeTotalItens > 0
                    ? $volumeTotalItens
                    : (float) ($dados['volume_total'] ?? 0);

                if ($volumeTotal <= 0) {
                    throw new \DomainException('Informe ao menos um item com quantidade válida para calcular o volume total do DOF.');
                }

                $dof = Dof::create([
                    'numero' => $numeroSerie,
                    'serie' => $numeroSerie !== '' ? $numeroSerie : null,
                    'valido_ate' => $dados['valido_ate'] ?? null,
                    'data_emissao' => $dados['data_emissao'] ?? now(),
                    'volume_total' => $volumeTotal,
                    'volume_saldo_m3' => $volumeTotal,
                    'unidade_medida' => $dados['unidade_medida'] ?? Dof::UNIDADE_M3,
                    'origem' => $dados['origem'] ?? null,
                    'destino' => $dados['destino'] ?? null,
                    'nota_fiscal' => $dados['nota_fiscal'] ?? null,
                    'status' => Dof::STATUS_ATIVO,
                ]);

                if (!empty($dados['itens'])) {
                    foreach ($dados['itens'] as $item) {
                        DofItem::create([
                            'dof_id' => $dof->id,
                            'especie_id' => $item['especie_id'],
                            'tipo' => $this->resolverTipoItemPorEspecie($item, $tiposEspecies),
                            'quantidade_autorizada' => $item['quantidade_autorizada'],
                            'quantidade_disponivel' => $item['quantidade_autorizada'],
                        ]);
                    }
                }

                return $dof->load('itens.especie');
            });
        } catch (\Throwable $e) {
            Log::error('Erro ao criar DOF', [
                'dados' => $dados,
                'erro' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function atualizar(string $id, array $dados): Dof
    {
        try {
            return DB::transaction(function () use ($id, $dados) {
                $dof = Dof::findOrFail($id);
                $numeroSerie = (string) ($dados['numero'] ?? $dados['serie'] ?? $dof->numero);
                $tiposEspecies = $this->resolverTiposEspeciesPorItem($dados['itens'] ?? []);

                $volumeTotalAnterior = (float) $dof->volume_total;
                $volumeTotalItens = $this->calcularVolumeTotalItens($dados['itens'] ?? []);
                $volumeTotalNovo = !empty($dados['itens'])
                    ? $volumeTotalItens
                    : (float) ($dados['volume_total'] ?? $volumeTotalAnterior);

                if ($volumeTotalNovo <= 0) {
                    throw new \DomainException('Volume total do DOF deve ser maior que zero.');
                }

                $dadosUpdate = [
                    'numero' => $numeroSerie,
                    'serie' => $numeroSerie,
                    'valido_ate' => $dados['valido_ate'] ?? $dof->valido_ate,
                    'data_emissao' => $dados['data_emissao'] ?? $dof->data_emissao,
                    'volume_total' => $volumeTotalNovo,
                    'origem' => $dados['origem'] ?? $dof->origem,
                    'destino' => $dados['destino'] ?? $dof->destino,
                    'nota_fiscal' => $dados['nota_fiscal'] ?? $dof->nota_fiscal,
                ];

                if (array_key_exists('unidade_medida', $dados)) {
                    $novaUnidade = $dados['unidade_medida'] ?? Dof::UNIDADE_M3;
                    if ($novaUnidade !== $dof->unidade_medida && $dof->dofLotes()->exists()) {
                        throw new \DomainException('Não é possível alterar a unidade de medida de um DOF que já possui alocações em lotes.');
                    }
                    $dadosUpdate['unidade_medida'] = $novaUnidade;
                }

                $dof->update($dadosUpdate);

                if (!empty($dados['itens'])) {
                    if ($dof->dofLotes()->exists()) {
                        throw new \DomainException('Não é possível alterar itens de um DOF que já possui alocações em lotes.');
                    }

                    $dof->itens()->delete();
                    foreach ($dados['itens'] as $item) {
                        DofItem::create([
                            'dof_id' => $dof->id,
                            'especie_id' => $item['especie_id'],
                            'tipo' => $this->resolverTipoItemPorEspecie($item, $tiposEspecies),
                            'quantidade_autorizada' => $item['quantidade_autorizada'],
                            'quantidade_disponivel' => $item['quantidade_disponivel'] ?? $item['quantidade_autorizada'],
                        ]);
                    }
                }

                $dof->recalcularSaldo();

                return $dof->fresh(['itens.especie', 'dofLotes.dofItem.especie', 'dofLotes.lote']);
            });
        } catch (\Throwable $e) {
            Log::error('Erro ao atualizar DOF', [
                'id' => $id,
                'dados' => $dados,
                'erro' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function excluir(string $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $dof = Dof::findOrFail($id);

                $volumeAlocado = $dof->getVolumeAlocadoAttribute();
                if ($volumeAlocado > 0) {
                    throw new \DomainException('Não é possível excluir DOF com volume alocado em lotes.');
                }

                $possuiSaidaRegistrada = $dof->movimentacoes()
                    ->where('tipo', Movimentacao::TIPO_BAIXA)
                    ->where('volume_m3', '>', 0)
                    ->exists();

                if ($possuiSaidaRegistrada) {
                    throw new \DomainException('Não é possível excluir DOF que já possui saída registrada.');
                }

                $dof->delete();
            });
        } catch (\Throwable $e) {
            Log::error('Erro ao excluir DOF', [
                'id' => $id,
                'erro' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function listarAtivos(): \Illuminate\Database\Eloquent\Collection
    {
        return Dof::with('itens.especie.tipoSerragem')
            ->whereIn('status', [Dof::STATUS_ATIVO, Dof::STATUS_PARCIAL])
            ->orderBy('numero')
            ->get();
    }

    public function resumo(array $filtros = []): array
    {
        $query = Dof::query();
        $this->aplicarFiltros($query, $filtros);

        $agora = now();
        
        $aggregate = $query->selectRaw("
            COUNT(*) as total_dofs,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as dofs_ativos,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as dofs_parciais,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as dofs_encerrados,
            SUM(CASE WHEN valido_ate < ? THEN 1 ELSE 0 END) as dofs_vencidos,
            SUM(volume_total) as volume_total_m3,
            SUM(volume_saldo_m3) as volume_saldo_m3
        ", [Dof::STATUS_ATIVO, Dof::STATUS_PARCIAL, Dof::STATUS_ENCERRADO, $agora])->first();

        $volumeTotal = (float) ($aggregate->volume_total_m3 ?? 0);
        $volumeSaldo = (float) ($aggregate->volume_saldo_m3 ?? 0);
        $volumeAlocado = max(0, $volumeTotal - $volumeSaldo);

        return [
            'total_dofs' => (int) ($aggregate->total_dofs ?? 0),
            'dofs_ativos' => (int) ($aggregate->dofs_ativos ?? 0),
            'dofs_parciais' => (int) ($aggregate->dofs_parciais ?? 0),
            'dofs_encerrados' => (int) ($aggregate->dofs_encerrados ?? 0),
            'dofs_vencidos' => (int) ($aggregate->dofs_vencidos ?? 0),
            'volume_total_m3' => $volumeTotal,
            'volume_saldo_m3' => $volumeSaldo,
            'volume_alocado_m3' => $volumeAlocado,
        ];
    }
}
