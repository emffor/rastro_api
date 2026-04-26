<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->text('comissao_motivo_anulacao')->nullable()->after('comissao_data_pagamento');
            $table->foreignUuid('comissao_usuario_anulacao_id')->nullable()->after('comissao_motivo_anulacao')->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropForeign(['comissao_usuario_anulacao_id']);
            $table->dropColumn(['comissao_motivo_anulacao', 'comissao_usuario_anulacao_id']);
        });
    }
};
