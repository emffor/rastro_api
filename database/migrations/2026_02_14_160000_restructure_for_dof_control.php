<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Dropar tabelas comerciais (ordem respeitando FKs)
        Schema::dropIfExists('patio_estoque_debitos');
        Schema::dropIfExists('patio_estoques');
        Schema::dropIfExists('produto_dof_item');
        Schema::dropIfExists('pedido_itens');
        Schema::dropIfExists('pedidos');
        Schema::dropIfExists('clientes');

        // 2. Dropar tabelas que dependem de produtos
        Schema::dropIfExists('lote_itens');
        Schema::dropIfExists('dof_distribuicoes');
        Schema::dropIfExists('movimentacoes_estoque');

        // 3. Agora pode dropar produtos
        Schema::dropIfExists('produtos');

        // 3. Ajustar tabela dofs
        Schema::table('dofs', function (Blueprint $table) {
            if (!Schema::hasColumn('dofs', 'volume_saldo_m3')) {
                $table->decimal('volume_saldo_m3', 12, 4)->default(0)->after('volume_total');
            }
        });

        // 4. Criar tabela dof_lotes (alocação DOF ↔ Lote)
        Schema::create('dof_lotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('dof_id');
            $table->uuid('lote_id');
            $table->decimal('volume_m3', 12, 4);
            $table->string('observacao')->nullable();
            $table->uuid('empresa_id');
            $table->timestamps();

            $table->foreign('dof_id')->references('id')->on('dofs')->onDelete('cascade');
            $table->foreign('lote_id')->references('id')->on('lotes')->onDelete('cascade');
            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');

            $table->index(['dof_id', 'lote_id']);
        });

        // 5. Criar tabela movimentacoes
        Schema::create('movimentacoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('dof_id');
            $table->uuid('lote_origem_id')->nullable();
            $table->uuid('lote_destino_id')->nullable();
            $table->string('tipo', 20); // ENTRADA, TRANSFERENCIA, BAIXA, AJUSTE
            $table->decimal('volume_m3', 12, 4);
            $table->string('observacao')->nullable();
            $table->uuid('usuario_id');
            $table->uuid('empresa_id');
            $table->timestamps();

            $table->foreign('dof_id')->references('id')->on('dofs')->onDelete('cascade');
            $table->foreign('lote_origem_id')->references('id')->on('lotes')->onDelete('set null');
            $table->foreign('lote_destino_id')->references('id')->on('lotes')->onDelete('set null');
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');

            $table->index('dof_id');
            $table->index('lote_origem_id');
            $table->index('lote_destino_id');
            $table->index('tipo');
            $table->index('created_at');
        });

        // 6. Remover campos comerciais de users (is_vendedor, comissao_percentual)
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_vendedor')) {
                $table->dropColumn('is_vendedor');
            }
            if (Schema::hasColumn('users', 'comissao_percentual')) {
                $table->dropColumn('comissao_percentual');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentacoes');
        Schema::dropIfExists('dof_lotes');

        Schema::table('dofs', function (Blueprint $table) {
            if (Schema::hasColumn('dofs', 'volume_saldo_m3')) {
                $table->dropColumn('volume_saldo_m3');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_vendedor')->default(false);
            $table->decimal('comissao_percentual', 5, 2)->nullable();
        });
    }
};
