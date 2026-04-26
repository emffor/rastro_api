<?php

namespace App\Services;

use App\Models\Movimentacao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;

class MovimentacaoService
{
    public function __construct(
        private readonly AdminMasterContextService $adminMasterContextService,
        private readonly AuditoriaService $auditoriaService,
    ) {}

    private function aplicarFiltros(Builder $query, array $filtros = []): Builder
    {
        if (!empty($filtros['dof_id'])) {
            $query->where('dof_id', $filtros['dof_id']);
        }

        if (!empty($filtros['lote_id'])) {
            $query->where(function (Builder $subQuery) use ($filtros) {
                $subQuery->where('lote_origem_id', $filtros['lote_id'])
                    ->orWhere('lote_destino_id', $filtros['lote_id']);
            });
        }

        if (!empty($filtros['tipo'])) {
            $query->where('tipo', $filtros['tipo']);
        }

        if (!empty($filtros['data_inicio'])) {
            $query->whereDate('created_at', '>=', $filtros['data_inicio']);
        }

        if (!empty($filtros['data_fim'])) {
            $query->whereDate('created_at', '<=', $filtros['data_fim']);
        }

        if (!empty($filtros['busca'])) {
            $busca = trim((string) $filtros['busca']);
            if ($busca !== '') {
                $query->where(function (Builder $subQuery) use ($busca) {
                    $subQuery->whereHas('dof', function (Builder $dofQuery) use ($busca) {
                        $dofQuery->where('numero', 'LIKE', "%{$busca}%");
                    })
                    ->orWhereHas('saidaOperacaoItem.notasFiscais', function (Builder $notaQuery) use ($busca) {
                        $notaQuery->where('numero_nf', 'LIKE', "%{$busca}%");
                    });
                });
            }
        }

        return $query;
    }

    private function novaConsulta()
    {
        return Movimentacao::with([
            'dof.itens.especie.tipoSerragem',
            'dof.anexosRelacionaveis',
            'dofItem.especie.tipoSerragem',
            'loteOrigem.patio',
            'loteDestino.patio',
            'usuario',
            'saidaOperacaoItem.especie.tipoSerragem',
            'saidaOperacaoItem.notasFiscais.anexosRelacionaveis.anexo',
            'saidaOperacaoItem.consumoProdutos',
        ])->orderBy('created_at', 'desc');
    }

    public function registrar(
        string $dofId,
        string $tipo,
        float $volumeM3,
        ?string $loteOrigemId = null,
        ?string $loteDestinoId = null,
        ?string $observacao = null,
        ?string $dofItemId = null,
        ?array $resumoProdutos = null,
        ?string $saidaOperacaoId = null,
        ?string $saidaOperacaoItemId = null,
    ): Movimentacao {
        try {
            $usuarioId = $this->adminMasterContextService->usuarioEfetivoId();

            if (!in_array($tipo, Movimentacao::tiposValidos(), true)) {
                throw new \InvalidArgumentException("Tipo de movimentação inválido: {$tipo}");
            }

            $movimentacao = Movimentacao::create([
                'dof_id' => $dofId,
                'dof_item_id' => $dofItemId,
                'saida_operacao_id' => $saidaOperacaoId,
                'saida_operacao_item_id' => $saidaOperacaoItemId,
                'lote_origem_id' => $loteOrigemId,
                'lote_destino_id' => $loteDestinoId,
                'tipo' => $tipo,
                'volume_m3' => $volumeM3,
                'resumo_produtos' => $resumoProdutos,
                'observacao' => $observacao,
                'usuario_id' => $usuarioId,
            ]);

            $this->auditoriaService->registrar('movimentacoes', 'movimentacao_registrada', $movimentacao);

            return $movimentacao;
        } catch (\Throwable $e) {
            Log::error('Erro ao registrar movimentação', [
                'dof_id' => $dofId,
                'dof_item_id' => $dofItemId,
                'tipo' => $tipo,
                'volume_m3' => $volumeM3,
                'erro' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function resumo(array $filtros = []): array
    {
        $query = Movimentacao::query();
        $this->aplicarFiltros($query, $filtros);

        $aggregate = $query->selectRaw("
            COUNT(*) as total_registros,
            SUM(volume_m3) as volume_total_m3,
            SUM(CASE WHEN tipo = ? THEN 1 ELSE 0 END) as entradas,
            SUM(CASE WHEN tipo = ? THEN 1 ELSE 0 END) as transferencias,
            SUM(CASE WHEN tipo = ? THEN 1 ELSE 0 END) as baixas,
            SUM(CASE WHEN tipo = ? THEN 1 ELSE 0 END) as ajustes
        ", [
            Movimentacao::TIPO_ENTRADA,
            Movimentacao::TIPO_TRANSFERENCIA,
            Movimentacao::TIPO_BAIXA,
            Movimentacao::TIPO_AJUSTE
        ])->first();

        return [
            'total_registros' => (int) ($aggregate->total_registros ?? 0),
            'volume_total_m3' => (float) ($aggregate->volume_total_m3 ?? 0),
            'quantidade_por_tipo' => [
                Movimentacao::TIPO_ENTRADA => (int) ($aggregate->entradas ?? 0),
                Movimentacao::TIPO_TRANSFERENCIA => (int) ($aggregate->transferencias ?? 0),
                Movimentacao::TIPO_BAIXA => (int) ($aggregate->baixas ?? 0),
                Movimentacao::TIPO_AJUSTE => (int) ($aggregate->ajustes ?? 0),
            ],
            // Keeping flat keys in case legacy UI expects it natively
            'entradas' => (int) ($aggregate->entradas ?? 0),
            'transferencias' => (int) ($aggregate->transferencias ?? 0),
            'baixas' => (int) ($aggregate->baixas ?? 0),
            'ajustes' => (int) ($aggregate->ajustes ?? 0),
        ];
    }

    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->novaConsulta();
        $this->aplicarFiltros($query, $filtros);

        if (isset($filtros['all']) && $filtros['all'] === 'true') {
            $items = $query->get();
            return new LengthAwarePaginator(
                $items,
                $items->count(),
                $items->count() ?: 1,
                1
            );
        }

        return $query->paginate($perPage);
    }

    public function listarParaRelatorio(array $filtros = []): Collection
    {
        $query = $this->novaConsulta();
        $this->aplicarFiltros($query, $filtros);
        return $query->get();
    }

    public function buscarPorId(string $id): Movimentacao
    {
        return Movimentacao::with(['dof.itens.especie.tipoSerragem', 'dof.anexosRelacionaveis', 'dofItem.especie.tipoSerragem', 'loteOrigem.patio', 'loteDestino.patio', 'usuario', 'saidaOperacaoItem.especie.tipoSerragem', 'saidaOperacaoItem.notasFiscais.anexosRelacionaveis.anexo', 'saidaOperacaoItem.consumoProdutos'])
            ->findOrFail($id);
    }

    public function listarPorDof(string $dofId): \Illuminate\Database\Eloquent\Collection
    {
        return Movimentacao::with(['dof.itens.especie.tipoSerragem', 'dof.anexosRelacionaveis', 'dofItem.especie.tipoSerragem', 'loteOrigem.patio', 'loteDestino.patio', 'usuario', 'saidaOperacaoItem.especie.tipoSerragem', 'saidaOperacaoItem.notasFiscais.anexosRelacionaveis.anexo', 'saidaOperacaoItem.consumoProdutos'])
            ->where('dof_id', $dofId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function listarPorLote(string $loteId, array $filtros = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->novaConsulta();

        $filtros['lote_id'] = $loteId;
        $this->aplicarFiltros($query, $filtros);

        return $query
            ->with([
            'dof.itens.especie',
            'dof.anexosRelacionaveis',
            'dofItem.especie',
            'loteOrigem.patio',
            'loteDestino.patio',
            'usuario',
            'saidaOperacaoItem.especie',
            'saidaOperacaoItem.notasFiscais.anexosRelacionaveis.anexo',
            'saidaOperacaoItem.consumoProdutos',
        ])
            ->get();
    }
}
