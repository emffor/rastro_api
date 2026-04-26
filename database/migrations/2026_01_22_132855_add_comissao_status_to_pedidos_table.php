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
            $table->string('comissao_status')->default('PENDENTE')->after('comissao_valor'); // PENDENTE, PAGO
            $table->timestamp('comissao_data_pagamento')->nullable()->after('comissao_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn(['comissao_status', 'comissao_data_pagamento']);
        });
    }
};
