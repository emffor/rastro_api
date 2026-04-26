<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Helpers\AuthHelper;

class UpdateDofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $numero = $this->input('numero');
        $numeroSerie = $this->input('numero_serie');
        
        $mergedNumero = $numero ?? $numeroSerie;

        $toMerge = [];

        if ($mergedNumero !== null) {
            $toMerge['numero'] = $mergedNumero;
            if (!$this->has('serie')) {
                $toMerge['serie'] = $mergedNumero;
            }
        }

        if ($this->has('itens')) {
            $itens = $this->input('itens');
            foreach ($itens as $index => $item) {
                if (isset($item['especie_id'])) {
                    $decrypted = AuthHelper::decryptId($item['especie_id']);
                    $itens[$index]['especie_id'] = $decrypted ?? $item['especie_id'];
                }
            }
            $toMerge['itens'] = $itens;
        }
        
        if ($this->has('empresa_id')) {
             $decryptedEmpresa = AuthHelper::decryptId($this->input('empresa_id'));
             $toMerge['empresa_id'] = $decryptedEmpresa ?? $this->input('empresa_id');
        }

        if (!empty($toMerge)) {
            $this->merge($toMerge);
        }
    }

    public function rules(): array
    {
        $empresaId = $this->input('empresa_id');
        $dofId = $this->route('id');
        $dofId = AuthHelper::decryptId($dofId) ?? $dofId;

        return [
            'numero' => [
                'sometimes',
                'string',
                Rule::unique('dofs', 'numero')
                    ->ignore($dofId)
                    ->where(function ($query) use ($empresaId) {
                        if ($empresaId) {
                            $query->where('empresa_id', $empresaId);
                        } else {
                            $query->whereNull('empresa_id');
                        }
                        $query->whereNull('deleted_at');
                    }),
            ],
            'serie' => 'nullable|string',
            'valido_ate' => 'sometimes|date',
            'data_emissao' => 'nullable|date',
            'volume_total' => 'nullable|numeric|min:0',
            'origem' => 'nullable|string',
            'destino' => 'nullable|string',
            'nota_fiscal' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('dofs', 'nota_fiscal')
                    ->ignore($dofId)
                    ->where(function ($query) use ($empresaId) {
                        if ($empresaId) {
                            $query->where('empresa_id', $empresaId);
                        } else {
                            $query->whereNull('empresa_id');
                        }
                        $query->whereNull('deleted_at');
                    }),
            ],
            'itens' => 'nullable|array',
            'itens.*.especie_id' => 'required_with:itens|uuid|exists:especies,id',
            'itens.*.tipo' => 'nullable|string',
            'itens.*.quantidade_autorizada' => 'required_with:itens|numeric|min:0.0001',
            'itens.*.quantidade_disponivel' => 'nullable|numeric|min:0',
        ];
    }
}
