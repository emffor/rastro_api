<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table) {
            if (!Schema::hasColumn('movimentacoes', 'saida_operacao_id')) {
                $table->foreignUuid('saida_operacao_id')
                    ->nullable()
                    ->after('dof_id')
                    ->constrained('saida_operacoes')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('movimentacoes', 'saida_operacao_item_id')) {
                $table->foreignUuid('saida_operacao_item_id')
                    ->nullable()
                    ->after('saida_operacao_id')
                    ->constrained('saida_operacao_itens')
                    ->nullOnDelete();
            }

            $table->index(['saida_operacao_id', 'tipo'], 'mov_saida_operacao_tipo_idx');
            $table->index('saida_operacao_item_id', 'mov_saida_operacao_item_idx');
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table) {
            if (Schema::hasColumn('movimentacoes', 'saida_operacao_item_id')) {
                $table->dropIndex('mov_saida_operacao_item_idx');
                $table->dropConstrainedForeignId('saida_operacao_item_id');
            }

            if (Schema::hasColumn('movimentacoes', 'saida_operacao_id')) {
                $table->dropIndex('mov_saida_operacao_tipo_idx');
                $table->dropConstrainedForeignId('saida_operacao_id');
            }
        });
    }
};
