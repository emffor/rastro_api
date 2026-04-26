<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Adicionar empresa_id em todas as tabelas de negócio
        $tabelas = ['especies', 'dofs', 'produtos', 'movimentacoes_estoque', 'auditorias'];

        foreach ($tabelas as $tabela) {
            if (Schema::hasTable($tabela) && !Schema::hasColumn($tabela, 'empresa_id')) {
                Schema::table($tabela, function (Blueprint $table) {
                    $table->foreignUuid('empresa_id')->nullable()->after('id')->constrained('empresas')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        $tabelas = ['especies', 'dofs', 'produtos', 'movimentacoes_estoque', 'auditorias'];

        foreach ($tabelas as $tabela) {
            if (Schema::hasTable($tabela) && Schema::hasColumn($tabela, 'empresa_id')) {
                Schema::table($tabela, function (Blueprint $table) use ($tabela) {
                    $table->dropForeign([$tabela . '_empresa_id_foreign']);
                    $table->dropColumn('empresa_id');
                });
            }
        }
    }
};
