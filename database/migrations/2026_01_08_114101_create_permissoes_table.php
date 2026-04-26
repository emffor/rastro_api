<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome')->unique(); // ex: produtos.criar, dofs.ver
            $table->string('descricao')->nullable();
            $table->string('grupo'); // ex: produtos, dofs, estoque
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissoes');
    }
};
