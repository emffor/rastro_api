<?php

namespace App\Http\Resources;

use App\Models\ProdutoDimensionado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Helpers\AuthHelper;

class MovimentacaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resumoProdutos = $this->montarResumoProdutosComCodigo();

        return [
            'id' => AuthHelper::encryptId($this->id),
            'identificador_legivel' => $this->criarIdentificadorLegivel(),
            'dof_id' => AuthHelper::encryptId($this->dof_id),
            'dof_item_id' => AuthHelper::encryptId($this->dof_item_id),
            'saida_operacao_id' => AuthHelper::encryptId($this->saida_operacao_id),
            'saida_operacao_item_id' => AuthHelper::encryptId($this->saida_operacao_item_id),
            'lote_origem_id' => AuthHelper::encryptId($this->lote_origem_id),
            'lote_destino_id' => AuthHelper::encryptId($this->lote_destino_id),
            'usuario_id' => AuthHelper::encryptId($this->usuario_id),
            'empresa_id' => AuthHelper::encryptId($this->empresa_id),
            'tipo' => $this->tipo,
            'volume_m3' => $this->volume_m3,
            'observacao' => $this->observacao,
            'created_at' => optional($this->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => optional($this->updated_at)->format('Y-m-d H:i:s'),
            'resumo_produtos' => $resumoProdutos,

            'dof' => $this->whenLoaded('dof', function () {
                return new DofResource($this->dof);
            }),

            'lote_origem' => $this->whenLoaded('loteOrigem', function () {
                return [
                    'id' => AuthHelper::encryptId($this->loteOrigem->id),
                    'patio_id' => AuthHelper::encryptId($this->loteOrigem->patio_id),
                    'nome' => $this->loteOrigem->nome,
                    'status' => $this->loteOrigem->status,
                    'capacidade_volume' => $this->loteOrigem->capacidade_volume,
                    'volume_ocupado' => $this->loteOrigem->volume_ocupado,
                    'patio' => $this->loteOrigem->patio ? [
                        'id' => AuthHelper::encryptId($this->loteOrigem->patio->id),
                        'nome' => $this->loteOrigem->patio->nome,
                    ] : null,
                ];
            }),

            'lote_destino' => $this->whenLoaded('loteDestino', function () {
                return [
                    'id' => AuthHelper::encryptId($this->loteDestino->id),
                    'patio_id' => AuthHelper::encryptId($this->loteDestino->patio_id),
                    'nome' => $this->loteDestino->nome,
                    'status' => $this->loteDestino->status,
                    'capacidade_volume' => $this->loteDestino->capacidade_volume,
                    'volume_ocupado' => $this->loteDestino->volume_ocupado,
                    'patio' => $this->loteDestino->patio ? [
                        'id' => AuthHelper::encryptId($this->loteDestino->patio->id),
                        'nome' => $this->loteDestino->patio->nome,
                    ] : null,
                ];
            }),

            'usuario' => $this->whenLoaded('usuario', function () {
                return [
                    'id' => AuthHelper::encryptId($this->usuario->id),
                    'name' => $this->usuario->name,
                    'email' => $this->usuario->email,
                ];
            }),

            'saida_operacao_item' => $this->whenLoaded('saidaOperacaoItem', function () {
                return [
                    'id' => AuthHelper::encryptId($this->saidaOperacaoItem->id),
                    'saida_operacao_id' => AuthHelper::encryptId($this->saidaOperacaoItem->saida_operacao_id),
                    'especie_id' => AuthHelper::encryptId($this->saidaOperacaoItem->especie_id),
                    'volume_solicitado_m3' => $this->saidaOperacaoItem->volume_solicitado_m3,
                    'volume_baixado_m3' => $this->saidaOperacaoItem->volume_baixado_m3,
                    'volume_sem_produto_m3' => $this->saidaOperacaoItem->volume_sem_produto_m3,
                    'observacao' => $this->saidaOperacaoItem->observacao,
                    'especie' => $this->saidaOperacaoItem->especie ? [
                        'id' => AuthHelper::encryptId($this->saidaOperacaoItem->especie->id),
                        'nome_popular' => $this->saidaOperacaoItem->especie->nome_popular,
                        'nome_cientifico' => $this->saidaOperacaoItem->especie->nome_cientifico,
                        'tipo_serragem_id' => AuthHelper::encryptId($this->saidaOperacaoItem->especie->tipo_serragem_id),
                        'tipo_serragem' => $this->saidaOperacaoItem->especie->tipoSerragem ? [
                            'id' => AuthHelper::encryptId($this->saidaOperacaoItem->especie->tipoSerragem->id),
                            'nome' => $this->saidaOperacaoItem->especie->tipoSerragem->nome,
                        ] : null,
                        'tipo' => $this->saidaOperacaoItem->especie->resolverTipoSerragemNome(),
                        'nome_tipo' => $this->saidaOperacaoItem->especie->nome_tipo,
                        'nome_formatado' => $this->saidaOperacaoItem->especie->nome_formatado,
                    ] : null,
                    'notas_fiscais' => $this->saidaOperacaoItem->notasFiscais?->map(function ($nota) {
                        $anexoNf = $this->resolverDadosAnexoNota($nota, 'anexo_nf');
                        $anexoDof = $this->resolverDadosAnexoNota($nota, 'anexo_dof');

                        return [
                            'id' => AuthHelper::encryptId($nota->id),
                            'saida_operacao_item_id' => AuthHelper::encryptId($nota->saida_operacao_item_id),
                            'numero_nf' => $nota->numero_nf,
                            'data_emissao_nf' => optional($nota->data_emissao_nf)->format('Y-m-d'),
                            'anexo_nf_path' => $anexoNf['path'],
                            'anexo_nf_url' => $anexoNf['url'],
                            'anexo_nf_original_name' => $anexoNf['original_name'],
                            'anexo_dof_path' => $anexoDof['path'],
                            'anexo_dof_url' => $anexoDof['url'],
                            'anexo_dof_original_name' => $anexoDof['original_name'],
                        ];
                    })->values(),
                    'consumo_produtos' => $this->saidaOperacaoItem->consumoProdutos?->map(function ($consumoProduto) {
                        return [
                            'id' => AuthHelper::encryptId($consumoProduto->id),
                            'saida_consumo_id' => AuthHelper::encryptId($consumoProduto->saida_consumo_id),
                            'saida_operacao_item_id' => AuthHelper::encryptId($consumoProduto->saida_operacao_item_id),
                            'produto_dimensionado_id' => AuthHelper::encryptId($consumoProduto->produto_dimensionado_id),
                            'produto_codigo' => $consumoProduto->produtoDimensionado?->codigo,
                            'quantidade_pecas' => $consumoProduto->quantidade_pecas,
                            'volume_unitario_m3' => $consumoProduto->volume_unitario_m3,
                            'volume_total_m3' => $consumoProduto->volume_total_m3,
                            'produto_nome_snapshot' => $consumoProduto->produto_nome_snapshot,
                        ];
                    })->values(),
                ];
            }),

            'dof_item' => $this->whenLoaded('dofItem', function () {
                return [
                    'id' => AuthHelper::encryptId($this->dofItem->id),
                    'dof_id' => AuthHelper::encryptId($this->dofItem->dof_id),
                    'especie_id' => AuthHelper::encryptId($this->dofItem->especie_id),
                    'tipo' => $this->dofItem->tipo,
                    'quantidade_autorizada' => $this->dofItem->quantidade_autorizada,
                    'quantidade_disponivel' => $this->dofItem->quantidade_disponivel,
                    'especie' => $this->dofItem->especie ? [
                        'id' => AuthHelper::encryptId($this->dofItem->especie->id),
                        'nome_popular' => $this->dofItem->especie->nome_popular,
                        'nome_cientifico' => $this->dofItem->especie->nome_cientifico,
                        'tipo_serragem_id' => AuthHelper::encryptId($this->dofItem->especie->tipo_serragem_id),
                        'tipo_serragem' => $this->dofItem->especie->tipoSerragem ? [
                            'id' => AuthHelper::encryptId($this->dofItem->especie->tipoSerragem->id),
                            'nome' => $this->dofItem->especie->tipoSerragem->nome,
                        ] : null,
                        'tipo' => $this->dofItem->especie->resolverTipoSerragemNome(),
                        'nome_tipo' => $this->dofItem->especie->nome_tipo,
                        'nome_formatado' => $this->dofItem->especie->nome_formatado,
                    ] : null,
                ];
            }),
        ];
    }

    private function criarIdentificadorLegivel(): string
    {
        $data = optional($this->created_at)->format('d/m/Y H:i');
        $tipoMap = [
            'ENTRADA' => 'Entrada',
            'TRANSFERENCIA' => 'Transferência',
            'BAIXA' => 'Baixa',
            'AJUSTE' => 'Ajuste',
        ];
        $tipoLabel = $tipoMap[$this->tipo] ?? $this->tipo;

        return "{$tipoLabel} - {$data}";
    }

    private function resolverDadosAnexoNota($nota, string $campo): array
    {
        $dados = [
            'path' => $nota->getAttribute("{$campo}_path"),
            'url' => $nota->getAttribute("{$campo}_url"),
            'original_name' => $nota->getAttribute("{$campo}_original_name"),
        ];

        if (!$nota->relationLoaded('anexosRelacionaveis')) {
            return $dados;
        }

        $relacionavel = $nota->anexosRelacionaveis
            ->first(fn ($relacionavel) => $relacionavel->campo === $campo && $relacionavel->anexo !== null);

        if (!$relacionavel) {
            return $dados;
        }

        return [
            'path' => $relacionavel->anexo->path,
            'url' => $relacionavel->anexo->url,
            'original_name' => $relacionavel->anexo->original_name,
        ];
    }

    private function montarResumoProdutosComCodigo(): ?array
    {
        $resumoProdutos = $this->resumo_produtos;
        if (!is_array($resumoProdutos) || empty($resumoProdutos)) {
            return $resumoProdutos;
        }

        $produtoIds = collect($resumoProdutos)
            ->pluck('produto_dimensionado_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        $codigosPorProdutoId = ProdutoDimensionado::withTrashed()
            ->whereIn('id', $produtoIds)
            ->pluck('codigo', 'id');

        $nomesProdutosSemCodigo = collect($resumoProdutos)
            ->filter(function (array $produto): bool {
                $codigo = trim((string) ($produto['produto_codigo'] ?? ''));
                $produtoId = trim((string) ($produto['produto_dimensionado_id'] ?? ''));
                $nome = trim((string) ($produto['produto_nome'] ?? ''));

                return $codigo === '' && $produtoId === '' && $nome !== '';
            })
            ->pluck('produto_nome')
            ->map(fn ($nome) => trim((string) $nome))
            ->unique()
            ->values();

        $codigosPorNome = ProdutoDimensionado::withTrashed()
            ->when($this->empresa_id, fn ($query) => $query->where('empresa_id', $this->empresa_id))
            ->whereIn('nome', $nomesProdutosSemCodigo)
            ->get(['nome', 'codigo'])
            ->groupBy('nome')
            ->map(function ($produtos) {
                return $produtos->count() === 1 ? $produtos->first()?->codigo : null;
            });

        return array_map(function (array $produto) use ($codigosPorProdutoId, $codigosPorNome): array {
            $produtoId = isset($produto['produto_dimensionado_id']) ? (string) $produto['produto_dimensionado_id'] : '';
            $produtoNome = trim((string) ($produto['produto_nome'] ?? ''));
            $codigoAtual = trim((string) ($produto['produto_codigo'] ?? ''));

            $produto['produto_codigo'] = $codigoAtual !== ''
                ? $codigoAtual
                : ($produtoId !== ''
                    ? $codigosPorProdutoId->get($produtoId)
                    : ($produtoNome !== '' ? $codigosPorNome->get($produtoNome) : null));

            return $produto;
        }, $resumoProdutos);
    }
}
