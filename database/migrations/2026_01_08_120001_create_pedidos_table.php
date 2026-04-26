<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignUuid('cliente_id')->constrained('clientes');
            $table->foreignUuid('vendedor_id')->constrained('users');
            $table->string('numero')->unique();
            $table->enum('status', ['RASCUNHO', 'PENDENTE', 'FINALIZADO', 'CANCELADO'])->default('RASCUNHO');
            $table->decimal('valor_total', 12, 2)->default(0);
            $table->decimal('comissao_percentual', 5, 2)->default(0);
            $table->decimal('comissao_valor', 10, 2)->default(0);
            $table->date('data_pedido');
            $table->date('data_finalizacao')->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
