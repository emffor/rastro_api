<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            // Remove a constraint global de numero único
            $table->dropUnique(['numero']);

            // Cria constraint composta: numero único POR empresa
            $table->unique(['empresa_id', 'numero'], 'pedidos_empresa_numero_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropUnique('pedidos_empresa_numero_unique');
            $table->unique('numero');
        });
    }
};
