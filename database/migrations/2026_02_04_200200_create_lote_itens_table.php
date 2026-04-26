<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lote_itens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lote_id')->constrained('lotes')->onDelete('cascade');
            $table->foreignUuid('dof_item_id')->nullable()->constrained('dof_itens')->onDelete('set null');
            $table->foreignUuid('produto_id')->nullable()->constrained('produtos')->onDelete('set null');
            $table->foreignUuid('dof_distribuicao_id')->nullable()->constrained('dof_distribuicoes')->onDelete('set null');
            $table->integer('quantidade')->default(0);
            $table->decimal('volume', 12, 6)->default(0);
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index('lote_id');
            $table->index('dof_item_id');
            $table->index('produto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lote_itens');
    }
};
