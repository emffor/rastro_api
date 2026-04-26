<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Builder;

class OrdenacaoHelper
{
    /**
     * Aplica ordenação em uma query Builder baseando-se em um array de campos permitidos.
     * 
     * @param Builder $query
     * @param string|null $campo Campo a ser ordenado
     * @param string|null $direcao Direção da ordenação (asc ou desc)
     * @param array $camposPermitidos Lista de campos que têm permissão de ordenação
     * @param string $campoPadrao Campo usado por padrão caso nenhum seja provido
     * @param string $direcaoPadrao Direção padrão
     * @return Builder
     */
    public static function aplicar(
        Builder $query,
        ?string $campo,
        ?string $direcao,
        array $camposPermitidos,
        string $campoPadrao = 'created_at',
        string $direcaoPadrao = 'desc'
    ): Builder {
        $campoFinal = in_array($campo, $camposPermitidos, true) ? $campo : $campoPadrao;
        
        $direcaoTratada = strtolower((string)$direcao);
        $direcaoFinal = in_array($direcaoTratada, ['asc', 'desc'], true) ? $direcaoTratada : strtolower($direcaoPadrao);

        return $query->orderBy($campoFinal, $direcaoFinal);
    }
}
