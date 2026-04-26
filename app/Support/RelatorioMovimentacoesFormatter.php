<?php

namespace App\Support;

use App\Models\Movimentacao;
use Illuminate\Support\Collection;

class RelatorioMovimentacoesFormatter
{
    public function resumo(Collection $movimentacoes): array
    {
        return [
            'total_registros' => $movimentacoes->count(),
            'volume_total_m3' => (float) $movimentacoes->sum('volume_m3'),
            'entradas' => $movimentacoes->where('tipo', Movimentacao::TIPO_ENTRADA)->count(),
            'transferencias' => $movimentacoes->where('tipo', Movimentacao::TIPO_TRANSFERENCIA)->count(),
            'baixas' => $movimentacoes->where('tipo', Movimentacao::TIPO_BAIXA)->count(),
            'ajustes' => $movimentacoes->where('tipo', Movimentacao::TIPO_AJUSTE)->count(),
        ];
    }

    public function linhas(Collection $movimentacoes, bool $usarAcentos = true): array
    {
        return $movimentacoes
            ->map(fn (Movimentacao $movimentacao): array => $this->linha($movimentacao, $usarAcentos))
            ->values()
            ->all();
    }

    public function linha(Movimentacao $movimentacao, bool $usarAcentos = true): array
    {
        return [
            'tipo' => $this->mapearTipo($movimentacao->tipo, $usarAcentos),
            'dof' => (string) ($movimentacao->dof?->numero ?: '—'),
            'nota_fiscal' => $this->formatarNotasFiscais($movimentacao),
            'lote_origem' => $this->formatarLote($movimentacao->loteOrigem, 'origem'),
            'lote_destino' => $this->formatarLote($movimentacao->loteDestino, 'destino'),
            'anexos' => $this->temAnexos($movimentacao) ? 'PDF' : '—',
            'especie' => $this->formatarEspecieMovimentacao($movimentacao, $usarAcentos),
            'produtos_pecas' => $this->formatarResumoProdutosSaida($movimentacao),
            'volume_m3' => number_format((float) $movimentacao->volume_m3, 4, $usarAcentos ? ',' : '.', $usarAcentos ? '.' : ''),
            'usuario' => (string) ($movimentacao->usuario?->name ?: '—'),
            'data' => optional($movimentacao->created_at)->format('d/m/Y H:i') ?: '—',
        ];
    }

    private function mapearTipo(string $tipo, bool $usarAcentos): string
    {
        return match ($tipo) {
            Movimentacao::TIPO_ENTRADA => 'Entrada',
            Movimentacao::TIPO_TRANSFERENCIA => $usarAcentos ? 'Transferência' : 'Transferencia',
            Movimentacao::TIPO_BAIXA => 'Baixa',
            Movimentacao::TIPO_AJUSTE => 'Ajuste',
            default => $tipo,
        };
    }

    private function formatarNotasFiscais(Movimentacao $movimentacao): string
    {
        $notasSaida = $movimentacao->saidaOperacaoItem?->notasFiscais
            ?->pluck('numero_nf')
            ->filter(fn ($numero): bool => trim((string) $numero) !== '')
            ->values()
            ->implode(', ');

        if ($notasSaida !== null && $notasSaida !== '') {
            return $notasSaida;
        }

        $notaDof = trim((string) ($movimentacao->dof?->nota_fiscal ?? ''));
        return $notaDof !== '' ? $notaDof : '—';
    }

    private function formatarLote($lote, string $tipo): string
    {
        $nomeLote = trim((string) ($lote?->nome ?? ''));
        if ($nomeLote === '') {
            return $tipo === 'origem' ? 'Entrada' : 'Saída';
        }

        return $nomeLote;
    }

    private function temAnexos(Movimentacao $movimentacao): bool
    {
        if ($movimentacao->tipo === Movimentacao::TIPO_ENTRADA) {
            return $movimentacao->dof?->relationLoaded('anexosRelacionaveis') === true
                && $movimentacao->dof->anexosRelacionaveis->isNotEmpty();
        }

        $notasFiscais = $movimentacao->saidaOperacaoItem?->notasFiscais;
        if (!$notasFiscais) {
            return false;
        }

        return $notasFiscais->contains(function ($nota): bool {
            if ($nota->relationLoaded('anexosRelacionaveis') && $nota->anexosRelacionaveis->isNotEmpty()) {
                return true;
            }

            return trim((string) ($nota->anexo_nf_url ?? '')) !== ''
                || trim((string) ($nota->anexo_dof_url ?? '')) !== ''
                || trim((string) ($nota->anexo_nf_path ?? '')) !== ''
                || trim((string) ($nota->anexo_dof_path ?? '')) !== '';
        });
    }

    private function formatarEspecieMovimentacao(Movimentacao $movimentacao, bool $usarAcentos): string
    {
        $especieSaida = $movimentacao->saidaOperacaoItem?->especie;
        if ($especieSaida) {
            return $this->formatarNomeEspecie(
                (string) ($especieSaida->tipoSerragem?->nome ?? $especieSaida->nome_tipo),
                (string) $especieSaida->nome_cientifico,
                (string) $especieSaida->nome_popular,
                (string) $especieSaida->nome_formatado,
            );
        }

        $especieDofItem = $movimentacao->dofItem?->especie;
        if ($especieDofItem) {
            return $this->formatarNomeEspecie(
                (string) ($especieDofItem->tipoSerragem?->nome ?? $especieDofItem->nome_tipo),
                (string) $especieDofItem->nome_cientifico,
                (string) $especieDofItem->nome_popular,
                (string) $especieDofItem->nome_formatado,
            );
        }

        $especiesDof = $movimentacao->dof?->itens
            ?->pluck('especie')
            ->filter();

        if (!$especiesDof || $especiesDof->isEmpty()) {
            return '—';
        }

        $especiesUnicas = $especiesDof->unique('id')->values();
        if ($especiesUnicas->count() > 1) {
            return ($usarAcentos ? 'Múltiplas espécies' : 'Multiplas especies') . ' (' . $especiesUnicas->count() . ')';
        }

        $especie = $especiesUnicas->first();
        return $this->formatarNomeEspecie(
            (string) ($especie->tipoSerragem?->nome ?? $especie->nome_tipo),
            (string) $especie->nome_cientifico,
            (string) $especie->nome_popular,
            (string) $especie->nome_formatado,
        );
    }

    private function formatarResumoProdutosSaida(Movimentacao $movimentacao): string
    {
        $resumoMovimentacao = collect($movimentacao->resumo_produtos ?? [])
            ->filter(fn ($registro) => is_array($registro))
            ->groupBy(fn (array $registro) => trim((string) ($registro['produto_nome'] ?? '')) ?: 'Produto')
            ->map(function (Collection $grupo, string $nome): string {
                $pecas = (int) $grupo->sum(fn (array $registro) => (int) ($registro['quantidade_pecas'] ?? 0));
                return "{$nome}: {$pecas}";
            })
            ->values()
            ->implode(' | ');

        if ($resumoMovimentacao !== '') {
            return $resumoMovimentacao;
        }

        $volumeSemProduto = (float) ($movimentacao->saidaOperacaoItem?->volume_sem_produto_m3 ?? 0);
        $consumoProdutos = $movimentacao->saidaOperacaoItem?->consumoProdutos;

        if (!$consumoProdutos || $consumoProdutos->isEmpty()) {
            return $volumeSemProduto > 0 ? 'Sem produto: ' . number_format($volumeSemProduto, 4, ',', '.') . ' m³' : '—';
        }

        $partes = $consumoProdutos
            ->groupBy(fn ($registro) => trim((string) $registro->produto_nome_snapshot) ?: 'Produto')
            ->map(function (Collection $grupo, string $nome): string {
                return "{$nome}: " . (int) $grupo->sum('quantidade_pecas');
            })
            ->values()
            ->all();

        if ($volumeSemProduto > 0) {
            $partes[] = 'Sem produto: ' . number_format($volumeSemProduto, 4, ',', '.') . ' m³';
        }

        return count($partes) > 0 ? implode(' | ', $partes) : '—';
    }

    private function formatarNomeEspecie(
        string $tipo,
        string $cientifico,
        string $popular,
        string $nomeFormatado,
    ): string {
        $tipo = trim($tipo);
        $cientifico = trim($cientifico);
        $popular = trim($popular);
        $nomeFormatado = trim($nomeFormatado);

        if ($tipo !== '' && $cientifico !== '' && $popular !== '') {
            return "{$tipo} / {$cientifico} - {$popular}";
        }

        if ($nomeFormatado !== '') {
            return $nomeFormatado;
        }

        if ($popular !== '') {
            return $popular;
        }

        if ($cientifico !== '') {
            return $cientifico;
        }

        return '—';
    }
}
