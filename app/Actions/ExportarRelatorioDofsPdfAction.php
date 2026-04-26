<?php

namespace App\Actions;

use App\Models\Dof;
use App\Support\RelatorioNomeArquivo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class ExportarRelatorioDofsPdfAction
{
    public function execute(Collection $dofs, array $filtros): Response
    {
        $volumeTotal = (float) $dofs->sum('volume_total');
        $volumeSaldo = (float) $dofs->sum('volume_saldo_m3');
        $volumeAlocado = $volumeTotal - $volumeSaldo;
        $percentualAlocado = $volumeTotal > 0 ? ($volumeAlocado / $volumeTotal) * 100 : 0;
        $agora = now();
        $empresaNome = (string) ($filtros['empresa_nome'] ?? '');

        $resumo = [
            'total_dofs' => $dofs->count(),
            'dofs_ativos' => $dofs->where('status', Dof::STATUS_ATIVO)->count(),
            'dofs_parciais' => $dofs->where('status', Dof::STATUS_PARCIAL)->count(),
            'dofs_encerrados' => $dofs->where('status', Dof::STATUS_ENCERRADO)->count(),
            'dofs_vencidos' => $dofs->filter(fn (Dof $dof) => optional($dof->valido_ate)?->lt($agora))->count(),
            'volume_total_m3' => $volumeTotal,
            'volume_saldo_m3' => $volumeSaldo,
            'volume_alocado_m3' => $volumeAlocado,
            'percentual_alocado' => $percentualAlocado,
        ];

        $pdf = Pdf::loadView('pdf.dofs-gerencial', [
            'dofs' => $dofs,
            'resumo' => $resumo,
            'filtro_busca' => (string) ($filtros['busca'] ?? ''),
            'empresa_nome' => $empresaNome !== '' ? $empresaNome : '—',
            'data_geracao' => now()->format('d/m/Y H:i'),
            'logo_path' => public_path('relatorios/logo.png'),
        ])->setPaper('a4', 'landscape');

        $nomeArquivo = RelatorioNomeArquivo::montar('relatorio-dofs', $empresaNome, 'pdf');
        return $pdf->download($nomeArquivo);
    }
}
