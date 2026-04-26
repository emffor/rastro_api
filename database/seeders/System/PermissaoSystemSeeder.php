<?php

namespace Database\Seeders\System;

use App\Models\Permissao;
use Illuminate\Database\Seeder;

class PermissaoSystemSeeder extends Seeder
{
    public function run(): void
    {
        $permissoes = [
            // Espécies
            ['nome' => 'especies.ver', 'descricao' => 'Visualizar espécies', 'grupo' => 'especies'],
            ['nome' => 'especies.criar', 'descricao' => 'Criar espécies', 'grupo' => 'especies'],
            ['nome' => 'especies.editar', 'descricao' => 'Editar espécies', 'grupo' => 'especies'],
            ['nome' => 'especies.excluir', 'descricao' => 'Excluir espécies', 'grupo' => 'especies'],

            // DOFs
            ['nome' => 'dofs.ver', 'descricao' => 'Visualizar DOFs', 'grupo' => 'dofs'],
            ['nome' => 'dofs.criar', 'descricao' => 'Criar DOFs', 'grupo' => 'dofs'],
            ['nome' => 'dofs.editar', 'descricao' => 'Editar DOFs', 'grupo' => 'dofs'],
            ['nome' => 'dofs.excluir', 'descricao' => 'Excluir DOFs', 'grupo' => 'dofs'],

            // Pátio
            ['nome' => 'patio.ver', 'descricao' => 'Visualizar pátios e lotes', 'grupo' => 'patio'],
            ['nome' => 'patio.criar', 'descricao' => 'Criar pátios e lotes', 'grupo' => 'patio'],
            ['nome' => 'patio.editar', 'descricao' => 'Editar pátios e lotes', 'grupo' => 'patio'],
            ['nome' => 'patio.excluir', 'descricao' => 'Excluir pátios e lotes', 'grupo' => 'patio'],

            // Usuários
            ['nome' => 'usuarios.ver', 'descricao' => 'Visualizar usuários', 'grupo' => 'usuarios'],
            ['nome' => 'usuarios.criar', 'descricao' => 'Criar usuários', 'grupo' => 'usuarios'],
            ['nome' => 'usuarios.editar', 'descricao' => 'Editar usuários', 'grupo' => 'usuarios'],
            ['nome' => 'usuarios.excluir', 'descricao' => 'Excluir usuários', 'grupo' => 'usuarios'],
            ['nome' => 'usuarios.ativar', 'descricao' => 'Ativar/Desativar usuários', 'grupo' => 'usuarios'],

            // Cargos
            ['nome' => 'cargos.ver', 'descricao' => 'Visualizar cargos', 'grupo' => 'cargos'],
            ['nome' => 'cargos.criar', 'descricao' => 'Criar cargos', 'grupo' => 'cargos'],
            ['nome' => 'cargos.editar', 'descricao' => 'Editar cargos', 'grupo' => 'cargos'],
            ['nome' => 'cargos.excluir', 'descricao' => 'Excluir cargos', 'grupo' => 'cargos'],

            // Produtos Dimensionados
            ['nome' => 'produtos_dimensionados.ver', 'descricao' => 'Visualizar produtos dimensionados', 'grupo' => 'produtos_dimensionados'],
            ['nome' => 'produtos_dimensionados.criar', 'descricao' => 'Criar produtos dimensionados', 'grupo' => 'produtos_dimensionados'],
            ['nome' => 'produtos_dimensionados.editar', 'descricao' => 'Editar produtos dimensionados', 'grupo' => 'produtos_dimensionados'],
            ['nome' => 'produtos_dimensionados.excluir', 'descricao' => 'Excluir produtos dimensionados', 'grupo' => 'produtos_dimensionados'],
        ];

        foreach ($permissoes as $p) {
            Permissao::firstOrCreate(['nome' => $p['nome']], $p);
        }
        
        $this->command->info('Tabela de permissões populada com sucesso!');
    }
}
