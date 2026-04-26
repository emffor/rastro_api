<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome');
            $table->string('descricao')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargos');
    }
};
