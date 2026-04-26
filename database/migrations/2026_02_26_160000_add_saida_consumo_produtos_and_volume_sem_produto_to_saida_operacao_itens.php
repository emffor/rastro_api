<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saida_operacao_itens', function (Blueprint $table) {
            if (!Schema::hasColumn('saida_operacao_itens', 'volume_sem_produto_m3')) {
                $table->decimal('volume_sem_produto_m3', 12, 4)->default(0)->after('volume_baixado_m3');
            }
        });

        Schema::create('saida_consumo_produtos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('saida_consumo_id');
            $table->uuid('saida_operacao_item_id');
            $table->uuid('produto_dimensionado_id')->nullable();
            $table->unsignedInteger('quantidade_pecas');
            $table->decimal('volume_unitario_m3', 12, 6);
            $table->decimal('volume_total_m3', 12, 4);
            $table->string('produto_nome_snapshot', 160);
            $table->timestamps();

            $table->foreign('saida_consumo_id')->references('id')->on('saida_consumos')->onDelete('cascade');
            $table->foreign('saida_operacao_item_id')->references('id')->on('saida_operacao_itens')->onDelete('cascade');
            $table->foreign('produto_dimensionado_id')->references('id')->on('produtos_dimensionados')->nullOnDelete();

            $table->index(['saida_consumo_id', 'created_at'], 'idx_saida_consumo_produtos_consumo_created');
            $table->index(['saida_operacao_item_id', 'created_at'], 'idx_saida_consumo_produtos_item_created');
            $table->index('produto_dimensionado_id', 'idx_saida_consumo_produtos_produto');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saida_consumo_produtos');

        Schema::table('saida_operacao_itens', function (Blueprint $table) {
            if (Schema::hasColumn('saida_operacao_itens', 'volume_sem_produto_m3')) {
                $table->dropColumn('volume_sem_produto_m3');
            }
        });
    }
};
