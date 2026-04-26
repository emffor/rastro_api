<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->timestamp('data_pedido', 0)->change();
            $table->timestamp('data_finalizacao', 0)->nullable()->change();
        });

        Schema::table('dofs', function (Blueprint $table) {
            $table->timestamp('data_emissao', 0)->nullable()->change();
            $table->timestamp('valido_ate', 0)->change();
        });

        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->timestamp('data_movimentacao', 0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->date('data_pedido')->change();
            $table->date('data_finalizacao')->nullable()->change();
        });

        Schema::table('dofs', function (Blueprint $table) {
            $table->date('data_emissao')->nullable()->change();
            $table->date('valido_ate')->change();
        });

        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->date('data_movimentacao')->change();
        });
    }
};
