<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->string('endereco')->nullable();
            $table->decimal('largura', 10, 2)->default(100);
            $table->decimal('altura', 10, 2)->default(100);
            $table->string('cor_fundo')->default('#4CAF50');
            $table->json('configuracao_mapa')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('empresa_id');
            $table->index('ativo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patios');
    }
};
