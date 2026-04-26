<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('auditorias');
    }

    public function down(): void
    {
        Schema::create('auditorias', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('acao');
            $table->string('alvo_tipo');
            $table->uuid('alvo_id');
            $table->json('valores_antigos')->nullable();
            $table->json('valores_novos')->nullable();
            $table->uuid('empresa_id')->nullable();
            $table->timestamps();
        });
    }
};
