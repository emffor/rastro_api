<?php

namespace App\Actions;

use App\Models\Dof;
use App\Support\RelatorioNomeArquivo;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportarRelatorioDofsExcelAction
{
    public function execute(Collection $dofs, array $filtros): StreamedResponse
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

        $nomeArquivo = RelatorioNomeArquivo::montar('relatorio-dofs', $empresaNome, 'csv');

        return response()->streamDownload(function () use ($dofs, $resumo, $filtros) {
            $output = fopen('php://output', 'w');

            if (!$output) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, ['Relatorio Gerencial de DOFs'], ';');
            fputcsv($output, ['Empresa', (string) ($filtros['empresa_nome'] ?? '—')], ';');
            fputcsv($output, ['Gerado em', now()->format('d/m/Y H:i')], ';');
            fputcsv($output, ['Filtro', (string) ($filtros['busca'] ?? 'Sem filtro')], ';');
            fputcsv($output, [''], ';');

            fputcsv($output, ['Resumo'], ';');
            fputcsv($output, ['Total de DOFs', $resumo['total_dofs']], ';');
            fputcsv($output, ['Volume total (m3)', number_format($resumo['volume_total_m3'], 4, '.', '')], ';');
            fputcsv($output, ['Volume alocado (m3)', number_format($resumo['volume_alocado_m3'], 4, '.', '')], ';');
            fputcsv($output, ['Saldo (m3)', number_format($resumo['volume_saldo_m3'], 4, '.', '')], ';');
            fputcsv($output, ['Percentual alocado (%)', number_format($resumo['percentual_alocado'], 2, '.', '')], ';');
            fputcsv($output, ['Nao alocados', $resumo['dofs_ativos']], ';');
            fputcsv($output, ['Parciais', $resumo['dofs_parciais']], ';');
            fputcsv($output, ['Alocados', $resumo['dofs_encerrados']], ';');
            fputcsv($output, ['Vencidos', $resumo['dofs_vencidos']], ';');
            fputcsv($output, [''], ';');

            fputcsv($output, [
                'Numero',
                'Origem',
                'Destino',
                'Validade',
                'Status',
                'Volume total (m3)',
                'Volume alocado (m3)',
                'Saldo (m3)',
                'Percentual alocado (%)',
            ], ';');

            foreach ($dofs as $dof) {
                $total = (float) $dof->volume_total;
                $saldo = (float) $dof->volume_saldo_m3;
                $alocado = max(0, $total - $saldo);
                $percentual = $total > 0 ? ($alocado / $total) * 100 : 0;
                $status = match ($dof->status) {
                    Dof::STATUS_ATIVO => 'NAO ALOCADO',
                    Dof::STATUS_PARCIAL => 'PARCIAL',
                    Dof::STATUS_ENCERRADO => 'ALOCADO',
                    default => (string) $dof->status,
                };

                fputcsv($output, [
                    (string) $dof->numero,
                    (string) ($dof->origem ?? '—'),
                    (string) ($dof->destino ?? '—'),
                    optional($dof->valido_ate)->format('d/m/Y H:i'),
                    $status,
                    number_format($total, 4, '.', ''),
                    number_format($alocado, 4, '.', ''),
                    number_format($saldo, 4, '.', ''),
                    number_format($percentual, 2, '.', ''),
                ], ';');
            }

            fclose($output);
        }, $nomeArquivo, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
