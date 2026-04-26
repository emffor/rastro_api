<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patio_estoques', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dof_item_id')->constrained('dof_itens')->onDelete('cascade');
            $table->foreignUuid('produto_id')->nullable()->constrained('produtos')->onDelete('set null');
            $table->foreignUuid('categoria_id')->nullable()->constrained('categorias')->onDelete('set null');
            $table->decimal('volume_disponivel', 12, 6)->default(0); // Saldo atual do pátio
            $table->decimal('volume_original', 12, 6)->default(0); // Volume original que entrou
            $table->decimal('volume_debitado', 12, 6)->default(0); // Total já debitado
            $table->timestamps();

            // Índices para performance
            $table->index(['categoria_id', 'volume_disponivel']);
            $table->index('created_at'); // Para FIFO
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patio_estoques');
    }
};
