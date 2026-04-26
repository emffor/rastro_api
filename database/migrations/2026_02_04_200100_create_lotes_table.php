<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('patio_id')->constrained('patios')->onDelete('cascade');
            $table->string('codigo')->index();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->decimal('pos_x', 10, 2)->default(0);
            $table->decimal('pos_y', 10, 2)->default(0);
            $table->decimal('largura', 10, 2)->default(50);
            $table->decimal('altura', 10, 2)->default(30);
            $table->decimal('rotacao', 5, 2)->default(0);
            $table->string('cor')->default('#FFFFFF');
            $table->string('cor_borda')->default('#333333');
            $table->enum('status', ['DISPONIVEL', 'OCUPADO', 'RESERVADO', 'BLOQUEADO'])->default('DISPONIVEL');
            $table->decimal('capacidade_volume', 12, 4)->nullable();
            $table->decimal('volume_ocupado', 12, 4)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['patio_id', 'codigo']);
            $table->index(['patio_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lotes');
    }
};
