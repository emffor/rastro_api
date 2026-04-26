<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Adicionar comissao_percentual em users
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('comissao_percentual', 5, 2)->nullable()->after('ativo');
        });

        // Adicionar campos de preço em produtos
        Schema::table('produtos', function (Blueprint $table) {
            $table->decimal('preco_compra', 10, 2)->nullable()->after('estoque_quantidade');
            $table->decimal('preco_venda', 10, 2)->nullable()->after('preco_compra');
            $table->enum('tipo_preco', ['UNIDADE', 'METRO_LINEAR', 'METRO_QUADRADO'])->default('UNIDADE')->after('preco_venda');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('comissao_percentual');
        });

        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn(['preco_compra', 'preco_venda', 'tipo_preco']);
        });
    }
};
