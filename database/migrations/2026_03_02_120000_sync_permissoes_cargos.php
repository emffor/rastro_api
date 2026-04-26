<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $agora = now();

        // ─── 1. Garantir que todas as 24 permissões existam ───
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

        foreach ($permissoes as $permissao) {
            $exists = DB::table('permissoes')
                ->where('nome', $permissao['nome'])
                ->exists();

            if (!$exists) {
                DB::table('permissoes')->insert([
                    'id' => (string) Str::uuid(),
                    'nome' => $permissao['nome'],
                    'descricao' => $permissao['descricao'],
                    'grupo' => $permissao['grupo'],
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
            }
        }

        // ─── 2. Buscar todos os IDs de permissões ───
        $todasPermissaoIds = DB::table('permissoes')->pluck('id')->toArray();

        // Mapeamento de permissões por padrão (mesmo do CriarCargosSeeder)
        $cargosConfig = [
            'Administrador' => ['*'],
            'Gerente' => ['patio.*', 'dofs.*', 'especies.*', 'usuarios.*', 'cargos.*', 'produtos_dimensionados.*'],
            'Operador' => ['patio.ver', 'patio.editar', 'dofs.ver', 'dofs.criar', 'especies.ver', 'produtos_dimensionados.ver'],
            'Estoquista' => ['patio.*', 'dofs.ver', 'especies.ver', 'produtos_dimensionados.ver'],
        ];

        // ─── 3. Para cada empresa, sincronizar cargos existentes ───
        $empresas = DB::table('empresas')->pluck('id');

        foreach ($empresas as $empresaId) {
            foreach ($cargosConfig as $cargoNome => $patterns) {
                $cargo = DB::table('cargos')
                    ->where('empresa_id', $empresaId)
                    ->where('nome', $cargoNome)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$cargo) {
                    continue;
                }

                // Resolver os IDs de permissões para este cargo
                $permissaoIds = $this->resolverPermissoes($patterns, $todasPermissaoIds);

                // Sincronizar: remover vinculações antigas e inserir novas
                DB::table('cargo_permissao')
                    ->where('cargo_id', $cargo->id)
                    ->delete();

                $inserts = [];
                foreach ($permissaoIds as $permissaoId) {
                    $inserts[] = [
                        'cargo_id' => $cargo->id,
                        'permissao_id' => $permissaoId,
                    ];
                }

                if (!empty($inserts)) {
                    DB::table('cargo_permissao')->insert($inserts);
                }
            }
        }
    }

    /**
     * Resolve padrões de permissão em IDs concretos.
     */
    private function resolverPermissoes(array $patterns, array $todasPermissaoIds): array
    {
        $ids = [];

        foreach ($patterns as $pattern) {
            if ($pattern === '*') {
                return $todasPermissaoIds;
            }

            if (str_ends_with($pattern, '.*')) {
                $grupo = str_replace('.*', '', $pattern);
                $grupoIds = DB::table('permissoes')
                    ->where('nome', 'like', "{$grupo}.%")
                    ->pluck('id')
                    ->toArray();
                $ids = array_merge($ids, $grupoIds);
            } else {
                $permissao = DB::table('permissoes')
                    ->where('nome', $pattern)
                    ->first();
                if ($permissao) {
                    $ids[] = $permissao->id;
                }
            }
        }

        return array_unique($ids);
    }

    public function down(): void
    {
        // Não é necessário reverter — as permissões devem permanecer
    }
};
