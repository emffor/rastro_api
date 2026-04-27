<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Helpers\AuthHelper;
use App\Models\Dof;

class StoreDofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize fallback field names and validate them.
     */
    protected function prepareForValidation(): void
    {
        $numero = $this->input('numero');
        $numeroSerie = $this->input('numero_serie');
        
        $mergedNumero = $numero ?? $numeroSerie;

        $this->merge([
            'numero' => $mergedNumero,
            'serie' => $this->input('serie') ?? $mergedNumero,
        ]);
        
        // Se houver IDs encriptados nos itens, podemos tratar aqui, 
        // mas a regra diz para descriptografar na entrada do Controller ou logo aqui.
        if ($this->has('itens')) {
            $itens = $this->input('itens');
            foreach ($itens as $index => $item) {
                if (isset($item['especie_id'])) {
                    $decrypted = AuthHelper::decryptId($item['especie_id']);
                    // Se a entrada vier crua (UUID UUID) ou já encriptada, validamos.
                    // Para evitar quebrar o sistema q ainda n manda criptografado, podemos fazer:
                    $itens[$index]['especie_id'] = $decrypted ?? $item['especie_id'];
                }
            }
            $this->merge(['itens' => $itens]);
        }
        
        if ($this->has('empresa_id')) {
             $decryptedEmpresa = AuthHelper::decryptId($this->input('empresa_id'));
             $this->merge(['empresa_id' => $decryptedEmpresa ?? $this->input('empresa_id')]);
        }
    }

    public function rules(): array
    {
        $empresaId = $this->input('empresa_id');

        return [
            'numero' => [
                'required',
                'string',
                Rule::unique('dofs', 'numero')
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
            'valido_ate' => 'required|date',
            'data_emissao' => 'nullable|date',
            'volume_total' => 'nullable|numeric|min:0',
            'origem' => 'nullable|string',
            'destino' => 'nullable|string',
            'nota_fiscal' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('dofs', 'nota_fiscal')
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
            'unidade_medida' => ['nullable', 'string', Rule::in(Dof::unidadesValidas())],
        ];
    }
}
