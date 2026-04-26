<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'observacao_geral' => 'nullable|string|max:500',
            'itens' => 'required|array|min:1',
            'itens.*.especie_id' => 'required|uuid|exists:especies,id',
            'itens.*.volume_m3' => 'required|numeric|min:0.0001',
            'itens.*.fontes_preferidas' => 'nullable|array',
            'itens.*.fontes_preferidas.*' => 'required_with:itens.*.fontes_preferidas|uuid|exists:dof_lotes,id',
            'itens.*.fontes_consumo' => 'nullable|array',
            'itens.*.fontes_consumo.*.dof_lote_id' => 'required_with:itens.*.fontes_consumo|uuid|exists:dof_lotes,id',
            'itens.*.fontes_consumo.*.volume_m3' => 'required_with:itens.*.fontes_consumo|numeric|min:0.0001',
            'itens.*.observacao' => 'nullable|string|max:500',
            'itens.*.notas_fiscais' => 'required|array|min:1',
            'itens.*.notas_fiscais.*.numero_nf' => 'required|string|max:100',
            'itens.*.notas_fiscais.*.data_emissao_nf' => 'required|date',
            'itens.*.baixa_produtos' => 'nullable|array',
            'itens.*.baixa_produtos.plano_token' => 'nullable|string',
            'itens.*.baixa_produtos.fontes' => 'nullable|array',
            'itens.*.baixa_produtos.fontes.*.dof_lote_id' => 'required_with:itens.*.baixa_produtos.fontes|uuid',
            'itens.*.baixa_produtos.fontes.*.linhas' => 'required_with:itens.*.baixa_produtos.fontes|array|min:1',
            'itens.*.baixa_produtos.fontes.*.linhas.*.produto_dimensionado_id' => 'required_with:itens.*.baixa_produtos.fontes.*.linhas|uuid|exists:produtos_dimensionados,id',
            'itens.*.baixa_produtos.fontes.*.linhas.*.quantidade_pecas' => 'required_with:itens.*.baixa_produtos.fontes.*.linhas|integer|min:1',
        ];
    }
}
