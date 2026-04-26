<?php

namespace App\Http\Controllers;

use App\Models\Dof;
use App\Models\DofLote;
use App\Models\Lote;
use App\Models\Movimentacao;
use App\Models\Patio;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $dofs = Dof::all();
            $patios = Patio::withCount('lotes')->where('ativo', true)->get();
            $lotes = Lote::whereIn('patio_id', $patios->pluck('id'))->get();
            $volumeEstoqueLotes = (float) DofLote::where('volume_m3', '>', 0)->sum('volume_m3');
            $dofsAtivosComEstoque = (int) DofLote::where('volume_m3', '>', 0)->distinct('dof_id')->count('dof_id');
            $volumeEntradas = (float) Movimentacao::where('tipo', Movimentacao::TIPO_ENTRADA)->sum('volume_m3');
            $volumeSaidas = (float) Movimentacao::where('tipo', Movimentacao::TIPO_BAIXA)->sum('volume_m3');
            $movimentacoesRecentes = Movimentacao::with(['dof.itens.especie', 'loteOrigem', 'loteDestino', 'usuario', 'saidaOperacaoItem.especie'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            $volumeTotalDofs = (float) $dofs->sum('volume_total');
            $volumeSaldoDofs = (float) $dofs->sum('volume_saldo_m3'); // Mantido para compatibilidade.
            $volumeAlocado = $volumeEstoqueLotes;
            $totalDofs = $dofs->count();
            $dofsSemEstoque = max(0, $totalDofs - $dofsAtivosComEstoque);

            return response()->json([
                'dados' => [
                    'resumo_dofs' => [
                        'total' => $totalDofs,
                        'ativos' => $dofs->where('status', Dof::STATUS_ATIVO)->count(),
                        'parciais' => $dofs->where('status', Dof::STATUS_PARCIAL)->count(),
                        'encerrados' => $dofs->where('status', Dof::STATUS_ENCERRADO)->count(),
                        'ativos_com_estoque' => $dofsAtivosComEstoque,
                        'sem_estoque' => $dofsSemEstoque,
                        'volume_total_m3' => $volumeTotalDofs,
                        'volume_saldo_m3' => $volumeSaldoDofs,
                        'volume_alocado_m3' => $volumeAlocado,
                    ],
                    'resumo_estoque' => [
                        'estoque_disponivel_m3' => $volumeEstoqueLotes,
                        'entradas_m3' => $volumeEntradas,
                        'saidas_m3' => $volumeSaidas,
                    ],
                    'resumo_patios' => [
                        'total' => $patios->count(),
                        'total_lotes' => $lotes->count(),
                        'volume_ocupado_m3' => (float) $lotes->sum('volume_ocupado'),
                        'capacidade_total_m3' => (float) $lotes->sum('capacidade_volume'),
                    ],
                    'patios' => $patios->map(fn (Patio $p) => [
                        'id' => $p->id,
                        'nome' => $p->nome,
                        'lotes_count' => $p->lotes_count,
                        'ativo' => $p->ativo,
                    ]),
                    'movimentacoes_recentes' => $movimentacoesRecentes,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['mensagem' => 'ERRO_DASHBOARD', 'erro' => $e->getMessage()], 500);
        }
    }
}
