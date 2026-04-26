<?php

namespace App\Actions;

use App\Support\RelatorioMovimentacoesFormatter;
use App\Support\RelatorioNomeArquivo;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportarRelatorioMovimentacoesExcelAction
{
    public function __construct(
        private readonly RelatorioMovimentacoesFormatter $formatter,
    ) {}

    public function execute(Collection $movimentacoes, array $filtros): StreamedResponse
    {
        $resumo = $this->formatter->resumo($movimentacoes);
        $linhas = $this->formatter->linhas($movimentacoes, false);
        $empresaNome = (string) ($filtros['empresa_nome'] ?? '');
        $nomeArquivo = RelatorioNomeArquivo::montar('relatorio-movimentacoes', $empresaNome, 'csv');

        return response()->streamDownload(function () use ($linhas, $resumo, $filtros) {
            $output = fopen('php://output', 'w');

            if (!$output) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, ['Relatorio de Movimentacoes'], ';');
            fputcsv($output, ['Empresa', (string) ($filtros['empresa_nome'] ?? '—')], ';');
            fputcsv($output, ['Gerado em', now()->format('d/m/Y H:i')], ';');
            fputcsv($output, ['Busca', (string) ($filtros['busca'] ?? 'Sem filtro')], ';');
            fputcsv($output, ['Tipo', (string) ($filtros['tipo'] ?? 'Todos')], ';');
            fputcsv($output, [''], ';');

            fputcsv($output, ['Resumo'], ';');
            fputcsv($output, ['Total de registros', $resumo['total_registros']], ';');
            fputcsv($output, ['Volume total (m3)', number_format($resumo['volume_total_m3'], 4, '.', '')], ';');
            fputcsv($output, ['Entradas', $resumo['entradas']], ';');
            fputcsv($output, ['Transferencias', $resumo['transferencias']], ';');
            fputcsv($output, ['Baixas', $resumo['baixas']], ';');
            fputcsv($output, ['Ajustes', $resumo['ajustes']], ';');
            fputcsv($output, [''], ';');

            fputcsv($output, [
                'Tipo',
                'DOF',
                'Nota Fiscal',
                'Lote Origem',
                'Lote Destino',
                'Anexos',
                'Item / Especie',
                'Produtos / Pecas',
                'Volume (m3)',
                'Usuario',
                'Data',
            ], ';');

            foreach ($linhas as $linha) {
                fputcsv($output, [
                    $linha['tipo'],
                    $linha['dof'],
                    $linha['nota_fiscal'],
                    $linha['lote_origem'],
                    $linha['lote_destino'],
                    $linha['anexos'],
                    $linha['especie'],
                    $linha['produtos_pecas'],
                    $linha['volume_m3'],
                    $linha['usuario'],
                    $linha['data'],
                ], ';');
            }

            fclose($output);
        }, $nomeArquivo, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
