<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dof_itens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dof_id')->constrained('dofs')->cascadeOnDelete();
            $table->foreignUuid('especie_id')->constrained('especies');
            $table->string('tipo'); // VIGA, PRANCHA, TORA, TABUA
            $table->decimal('quantidade_autorizada', 10, 4); // M³ do documento
            $table->decimal('quantidade_disponivel', 10, 4); // Saldo atual M³
            $table->timestamps();
            
            $table->unique(['dof_id', 'especie_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dof_itens');
    }
};
