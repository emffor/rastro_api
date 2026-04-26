<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $agora = now();
        $permissoes = [
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
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'nome' => $permissao['nome'],
                    'descricao' => $permissao['descricao'],
                    'grupo' => $permissao['grupo'],
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permissoes')
            ->whereIn('nome', [
                'produtos_dimensionados.ver',
                'produtos_dimensionados.criar',
                'produtos_dimensionados.editar',
                'produtos_dimensionados.excluir',
            ])
            ->delete();
    }
};
