<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_itens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignUuid('produto_id')->constrained('produtos');
            $table->decimal('quantidade', 10, 4);
            $table->decimal('preco_unitario', 10, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_itens');
    }
};
