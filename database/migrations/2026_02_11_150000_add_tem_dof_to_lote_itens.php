<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lote_itens', function (Blueprint $table) {
            $table->boolean('tem_dof')->default(true)->after('observacao');
            $table->foreignUuid('movimentacao_estoque_id')
                ->nullable()
                ->after('tem_dof')
                ->constrained('movimentacoes_estoque')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('lote_itens', function (Blueprint $table) {
            $table->dropForeign(['movimentacao_estoque_id']);
            $table->dropColumn(['tem_dof', 'movimentacao_estoque_id']);
        });
    }
};
