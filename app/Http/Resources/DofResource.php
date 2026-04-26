<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Helpers\AuthHelper;

class DofResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dofLotes = $this->whenLoaded('dofLotes', function () {
            return $this->dofLotes->map(function ($dofLote) {
                return [
                    'id' => AuthHelper::encryptId($dofLote->id),
                    'dof_id' => AuthHelper::encryptId($dofLote->dof_id),
                    'dof_item_id' => AuthHelper::encryptId($dofLote->dof_item_id),
                    'lote_id' => AuthHelper::encryptId($dofLote->lote_id),
                    'volume_m3' => $dofLote->volume_m3,
                    'observacao' => $dofLote->observacao,
                    'empresa_id' => AuthHelper::encryptId($dofLote->empresa_id),
                    'created_at' => optional($dofLote->created_at)->format('Y-m-d H:i:s'),
                    'updated_at' => optional($dofLote->updated_at)->format('Y-m-d H:i:s'),
                ];
            });
        });

        return [
            'id' => AuthHelper::encryptId($this->id),
            'numero' => $this->numero,
            'serie' => $this->serie,
            'valido_ate' => optional($this->valido_ate)->format('Y-m-d H:i:s'),
            'data_emissao' => optional($this->data_emissao)->format('Y-m-d H:i:s'),
            'volume_total' => $this->volume_total,
            'volume_saldo_m3' => $this->volume_saldo_m3,
            'origem' => $this->origem,
            'destino' => $this->destino,
            'nota_fiscal' => $this->nota_fiscal,
            'status' => $this->status,
            'volume_alocado' => $this->volume_alocado,
            'possui_anexos' => $this->relationLoaded('anexosRelacionaveis') && $this->anexosRelacionaveis->isNotEmpty(),
            'empresa_id' => AuthHelper::encryptId($this->empresa_id),
            'itens' => $this->whenLoaded('itens', function () {
                return $this->itens->map(function ($item) {
                    return [
                        'id' => AuthHelper::encryptId($item->id),
                        'dof_id' => AuthHelper::encryptId($item->dof_id),
                        'especie_id' => AuthHelper::encryptId($item->especie_id),
                        'tipo' => $item->tipo,
                        'quantidade_autorizada' => $item->quantidade_autorizada,
                        'quantidade_disponivel' => $item->quantidade_disponivel,
                        'created_at' => optional($item->created_at)->format('Y-m-d H:i:s'),
                        'updated_at' => optional($item->updated_at)->format('Y-m-d H:i:s'),
                        'especie' => $item->especie ? [
                            'id' => AuthHelper::encryptId($item->especie->id),
                            'nome_popular' => $item->especie->nome_popular,
                            'nome_cientifico' => $item->especie->nome_cientifico,
                            'tipo_serragem_id' => AuthHelper::encryptId($item->especie->tipo_serragem_id),
                            'tipo_serragem' => $item->especie->tipoSerragem ? [
                                'id' => AuthHelper::encryptId($item->especie->tipoSerragem->id),
                                'nome' => $item->especie->tipoSerragem->nome,
                            ] : null,
                            'tipo' => $item->especie->resolverTipoSerragemNome(),
                            'nome_tipo' => $item->especie->nome_tipo,
                            'nome_formatado' => $item->especie->nome_formatado,
                        ] : null,
                    ];
                });
            }),
            'dof_lotes' => $dofLotes,
            'dofLotes' => $dofLotes,
            'movimentacoes' => $this->whenLoaded('movimentacoes', function () {
                return $this->movimentacoes->map(function ($movimentacao) {
                    return [
                        'id' => AuthHelper::encryptId($movimentacao->id),
                        'dof_id' => AuthHelper::encryptId($movimentacao->dof_id),
                        'dof_item_id' => AuthHelper::encryptId($movimentacao->dof_item_id),
                        'saida_operacao_id' => AuthHelper::encryptId($movimentacao->saida_operacao_id),
                        'saida_operacao_item_id' => AuthHelper::encryptId($movimentacao->saida_operacao_item_id),
                        'lote_origem_id' => AuthHelper::encryptId($movimentacao->lote_origem_id),
                        'lote_destino_id' => AuthHelper::encryptId($movimentacao->lote_destino_id),
                        'tipo' => $movimentacao->tipo,
                        'volume_m3' => $movimentacao->volume_m3,
                        'observacao' => $movimentacao->observacao,
                        'usuario_id' => AuthHelper::encryptId($movimentacao->usuario_id),
                        'created_at' => optional($movimentacao->created_at)->format('Y-m-d H:i:s'),
                        'updated_at' => optional($movimentacao->updated_at)->format('Y-m-d H:i:s'),
                    ];
                });
            }),
        ];
    }
}
