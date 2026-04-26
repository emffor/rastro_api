<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToEmpresa
{
    /**
     * Boot the BelongsToEmpresa trait.
     */
    protected static function bootBelongsToEmpresa()
    {
        // Adiciona escopo global para filtrar por empresa
        static::addGlobalScope('empresa_id', function (Builder $builder) {
            $empresaId = request()->get('empresa_id');
            
            // Só aplica o filtro se houver um empresa_id definido na requisição (via Middleware ou manual)
            // Se for nulo (ex: MASTER acessando dados globais ou fora de contexto), não filtra (CUIDADO)
            // Mas o Middleware EmpresaScope GARANTE que empresa_id seja setado para usuários logados.
            if ($empresaId) {
                $builder->where($builder->getQuery()->from . '.empresa_id', $empresaId);
            }
        });

        // Preenche automaticamente o empresa_id ao criar
        static::creating(function ($model) {
            $empresaId = request()->get('empresa_id');
            if ($empresaId && !$model->empresa_id) {
                $model->empresa_id = $empresaId;
            }
        });
    }

    /**
     * Relacionamento com a Empresa.
     */
    public function empresa()
    {
        return $this->belongsTo(\App\Models\Empresa::class);
    }
}
