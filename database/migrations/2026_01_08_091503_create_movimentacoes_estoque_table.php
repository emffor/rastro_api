<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimentacoes_estoque', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('produto_id')->constrained('produtos');
            $table->string('tipo'); // ENTRADA, SAIDA, AJUSTE
            $table->decimal('quantidade', 10, 4);
            $table->date('data_movimentacao');
            $table->text('observacao')->nullable();
            $table->foreignUuid('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentacoes_estoque');
    }
};
