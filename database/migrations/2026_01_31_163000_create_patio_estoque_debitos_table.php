<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patio_estoque_debitos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('categoria_id')->constrained('categorias')->onDelete('cascade');
            $table->decimal('volume_devedor', 12, 6)->default(0);
            $table->timestamps();

            // Garante apenas um registro de débito por categoria (opcional, mas recomendado para simplificar a lógica)
            // Se houver múltiplos, a lógica de soma funciona, mas um único registro é mais limpo.
            // Para simplificar a lógica de "pilha" de dívida, vamos deixar n-registros mas idealmente agruparíamos.
            // Vou optar por permitir múltiplos registros para ter histórico se necessário, mas a lógica vai somar.
            // Melhor: Um registro único atualizável por categoria é mais fácil de gerenciar atomicamente e evita fragmentação.
            // Vou adicionar unique constraint na categoria? Não necessariamente, pode ser melhor ter logs de débitos separados?
            // O plano diz "Tabela para armazenar os débitos pendentes".
            // Para simplificar a baixa posterior (abater da dívida), ter um saldo único por categoria é muito mais simples.
            // Decisão: Unique constraint na categoria_id?
            // Se eu tiver unique, eu tenho que fazer upsert (updateOrInsert).
            // Se eu não tiver, eu tenho que somar todos e ir deletando/atualizando.
            // Vamos manter simples: permite múltiplos, abate do mais antigo (FIFO de dívida? não faz sentido, dívida é dívida).
            // Vamos manter simples: um registro por tentativa de débito que falhou (ficou negativo).
            // Assim podemos rastrear qual 'venda' gerou a dívida se quisermos adicionar metadados depois.
            
            $table->index('categoria_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patio_estoque_debitos');
    }
};
