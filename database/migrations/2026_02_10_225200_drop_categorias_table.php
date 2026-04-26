<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remover permissões de categorias
        DB::table('permissoes')->where('grupo', 'categorias')->delete();

        // Dropar a tabela categorias (obsoleta após migração para espécies)
        Schema::dropIfExists('categorias');
    }

    public function down(): void
    {
        // Recriar tabela categorias
        Schema::create('categorias', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome');
            $table->foreignUuid('empresa_id')->constrained('empresas');
            $table->softDeletes();
            $table->timestamps();
        });

        // Recriar permissões de categorias
        DB::table('permissoes')->insert([
            ['nome' => 'categorias.ver', 'descricao' => 'Visualizar categorias', 'grupo' => 'categorias', 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'categorias.criar', 'descricao' => 'Criar categorias', 'grupo' => 'categorias', 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'categorias.editar', 'descricao' => 'Editar categorias', 'grupo' => 'categorias', 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'categorias.excluir', 'descricao' => 'Excluir categorias', 'grupo' => 'categorias', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
};
