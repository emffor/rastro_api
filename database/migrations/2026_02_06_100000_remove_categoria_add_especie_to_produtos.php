<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Adicionar especie_id e tipo_madeira na tabela produtos
        Schema::table('produtos', function (Blueprint $table) {
            $table->foreignUuid('especie_id')->nullable()->after('categoria_id')->constrained('especies')->nullOnDelete();
            $table->string('tipo_madeira')->nullable()->after('especie_id');
        });

        // 2. Migrar dados: inferir especie_id e tipo_madeira a partir da categoria
        // Para cada produto com categoria, buscar os dof_itens da mesma categoria
        // e pegar especie_id e tipo do primeiro item encontrado
        DB::statement("
            UPDATE produtos p
            SET especie_id = sub.especie_id,
                tipo_madeira = sub.tipo
            FROM (
                SELECT DISTINCT ON (di.categoria_id) 
                    di.categoria_id,
                    di.especie_id,
                    di.tipo
                FROM dof_itens di
                WHERE di.categoria_id IS NOT NULL
                ORDER BY di.categoria_id, di.created_at ASC
            ) sub
            WHERE p.categoria_id = sub.categoria_id
            AND p.categoria_id IS NOT NULL
        ");

        // 3. Adicionar especie_id e tipo nas tabelas de pátio (para substituir categoria_id)
        Schema::table('patio_estoques', function (Blueprint $table) {
            $table->foreignUuid('especie_id')->nullable()->after('categoria_id')->constrained('especies')->nullOnDelete();
            $table->string('tipo_madeira')->nullable()->after('especie_id');
        });

        // Migrar dados do patio_estoques
        DB::statement("
            UPDATE patio_estoques pe
            SET especie_id = di.especie_id,
                tipo_madeira = di.tipo
            FROM dof_itens di
            WHERE pe.dof_item_id = di.id
        ");

        // Adicionar índice para performance
        Schema::table('patio_estoques', function (Blueprint $table) {
            $table->index(['especie_id', 'tipo_madeira', 'volume_disponivel'], 'patio_estoques_especie_tipo_volume_idx');
        });

        // 4. Refatorar patio_estoque_debitos: trocar categoria_id por especie_id + tipo_madeira
        Schema::table('patio_estoque_debitos', function (Blueprint $table) {
            $table->foreignUuid('especie_id')->nullable()->after('categoria_id')->constrained('especies')->nullOnDelete();
            $table->string('tipo_madeira')->nullable()->after('especie_id');
        });

        // Migrar dados dos débitos
        DB::statement("
            UPDATE patio_estoque_debitos ped
            SET especie_id = sub.especie_id,
                tipo_madeira = sub.tipo
            FROM (
                SELECT DISTINCT ON (di.categoria_id)
                    di.categoria_id,
                    di.especie_id,
                    di.tipo
                FROM dof_itens di
                WHERE di.categoria_id IS NOT NULL
                ORDER BY di.categoria_id, di.created_at ASC
            ) sub
            WHERE ped.categoria_id = sub.categoria_id
            AND ped.categoria_id IS NOT NULL
        ");

        Schema::table('patio_estoque_debitos', function (Blueprint $table) {
            $table->index(['especie_id', 'tipo_madeira'], 'patio_debitos_especie_tipo_idx');
        });

        // 5. Remover categoria_id das tabelas (após migração de dados)
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropForeign(['categoria_id']);
            $table->dropColumn('categoria_id');
        });

        Schema::table('dof_itens', function (Blueprint $table) {
            $table->dropForeign(['categoria_id']);
            $table->dropColumn('categoria_id');
        });

        Schema::table('patio_estoques', function (Blueprint $table) {
            $table->dropIndex(['categoria_id', 'volume_disponivel']);
            $table->dropForeign(['categoria_id']);
            $table->dropColumn('categoria_id');
        });

        Schema::table('patio_estoque_debitos', function (Blueprint $table) {
            $table->dropIndex(['categoria_id']);
            $table->dropForeign(['categoria_id']);
            $table->dropColumn('categoria_id');
        });
    }

    public function down(): void
    {
        // Restaurar categoria_id nas tabelas
        Schema::table('produtos', function (Blueprint $table) {
            $table->foreignUuid('categoria_id')->nullable()->constrained('categorias');
        });

        Schema::table('dof_itens', function (Blueprint $table) {
            $table->foreignUuid('categoria_id')->nullable()->constrained('categorias');
        });

        Schema::table('patio_estoques', function (Blueprint $table) {
            $table->foreignUuid('categoria_id')->nullable()->constrained('categorias')->onDelete('set null');
            $table->index(['categoria_id', 'volume_disponivel']);
        });

        Schema::table('patio_estoque_debitos', function (Blueprint $table) {
            $table->foreignUuid('categoria_id')->nullable()->constrained('categorias')->onDelete('cascade');
            $table->index('categoria_id');
        });

        // Remover novos campos
        Schema::table('patio_estoque_debitos', function (Blueprint $table) {
            $table->dropIndex('patio_debitos_especie_tipo_idx');
            $table->dropForeign(['especie_id']);
            $table->dropColumn(['especie_id', 'tipo_madeira']);
        });

        Schema::table('patio_estoques', function (Blueprint $table) {
            $table->dropIndex('patio_estoques_especie_tipo_volume_idx');
            $table->dropForeign(['especie_id']);
            $table->dropColumn(['especie_id', 'tipo_madeira']);
        });

        Schema::table('produtos', function (Blueprint $table) {
            $table->dropForeign(['especie_id']);
            $table->dropColumn(['especie_id', 'tipo_madeira']);
        });
    }
};
