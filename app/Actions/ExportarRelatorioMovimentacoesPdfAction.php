<?php

namespace App\Actions;

use App\Support\RelatorioMovimentacoesFormatter;
use App\Support\RelatorioNomeArquivo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class ExportarRelatorioMovimentacoesPdfAction
{
    public function __construct(
        private readonly RelatorioMovimentacoesFormatter $formatter,
    ) {}

    public function execute(Collection $movimentacoes, array $filtros): Response
    {
        $empresaNome = (string) ($filtros['empresa_nome'] ?? '');

        $pdf = Pdf::loadView('pdf.movimentacoes-gerencial', [
            'linhas' => $this->formatter->linhas($movimentacoes),
            'resumo' => $this->formatter->resumo($movimentacoes),
            'filtro_busca' => (string) ($filtros['busca'] ?? ''),
            'filtro_tipo' => (string) ($filtros['tipo'] ?? ''),
            'empresa_nome' => $empresaNome !== '' ? $empresaNome : '—',
            'data_geracao' => now()->format('d/m/Y H:i'),
            'logo_path' => public_path('relatorios/logo.png'),
        ])->setPaper('a4', 'landscape');

        $nomeArquivo = RelatorioNomeArquivo::montar('relatorio-movimentacoes', $empresaNome, 'pdf');
        return $pdf->download($nomeArquivo);
    }
}
