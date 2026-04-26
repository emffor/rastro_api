<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produtos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->string('unidade'); // M3, UN, M, KG
            $table->string('tipo'); // FLORESTAL, GERAL
            $table->foreignUuid('dof_id')->nullable()->constrained('dofs')->nullOnDelete();
            $table->foreignUuid('especie_id')->nullable()->constrained('especies')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produtos');
    }
};
