<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saida_operacoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('empresa_id');
            $table->uuid('usuario_id');
            $table->string('observacao')->nullable();
            $table->timestamps();

            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('cascade');

            $table->index(['empresa_id', 'created_at']);
        });

        Schema::create('saida_operacao_itens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('saida_operacao_id');
            $table->uuid('especie_id');
            $table->decimal('volume_solicitado_m3', 12, 4);
            $table->decimal('volume_baixado_m3', 12, 4);
            $table->string('observacao')->nullable();
            $table->timestamps();

            $table->foreign('saida_operacao_id')->references('id')->on('saida_operacoes')->onDelete('cascade');
            $table->foreign('especie_id')->references('id')->on('especies')->onDelete('cascade');

            $table->index(['saida_operacao_id', 'especie_id']);
        });

        Schema::create('saida_operacao_item_notas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('saida_operacao_item_id');
            $table->string('numero_nf', 100);
            $table->date('data_emissao_nf');
            $table->timestamps();

            $table->foreign('saida_operacao_item_id')->references('id')->on('saida_operacao_itens')->onDelete('cascade');

            $table->index('saida_operacao_item_id');
            $table->index(['numero_nf', 'data_emissao_nf']);
        });

        Schema::create('saida_consumos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('saida_operacao_item_id');
            $table->uuid('dof_id');
            $table->uuid('dof_item_id');
            $table->uuid('dof_lote_id')->nullable();
            $table->uuid('lote_id');
            $table->decimal('volume_m3', 12, 4);
            $table->timestamps();

            $table->foreign('saida_operacao_item_id')->references('id')->on('saida_operacao_itens')->onDelete('cascade');
            $table->foreign('dof_id')->references('id')->on('dofs')->onDelete('cascade');
            $table->foreign('dof_item_id')->references('id')->on('dof_itens')->onDelete('cascade');
            $table->foreign('dof_lote_id')->references('id')->on('dof_lotes')->nullOnDelete();
            $table->foreign('lote_id')->references('id')->on('lotes')->onDelete('cascade');

            $table->index(['saida_operacao_item_id', 'created_at']);
            $table->index(['dof_id', 'dof_item_id']);
            $table->index('lote_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saida_consumos');
        Schema::dropIfExists('saida_operacao_item_notas');
        Schema::dropIfExists('saida_operacao_itens');
        Schema::dropIfExists('saida_operacoes');
    }
};
