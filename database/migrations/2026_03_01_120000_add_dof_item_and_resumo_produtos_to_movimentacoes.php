<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table) {
            if (!Schema::hasColumn('movimentacoes', 'dof_item_id')) {
                $table->foreignUuid('dof_item_id')
                    ->nullable()
                    ->after('dof_id')
                    ->constrained('dof_itens')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('movimentacoes', 'resumo_produtos')) {
                $table->json('resumo_produtos')
                    ->nullable()
                    ->after('volume_m3');
            }

            $table->index('dof_item_id', 'mov_dof_item_idx');
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table) {
            if (Schema::hasColumn('movimentacoes', 'dof_item_id')) {
                $table->dropIndex('mov_dof_item_idx');
                $table->dropConstrainedForeignId('dof_item_id');
            }

            if (Schema::hasColumn('movimentacoes', 'resumo_produtos')) {
                $table->dropColumn('resumo_produtos');
            }
        });
    }
};
