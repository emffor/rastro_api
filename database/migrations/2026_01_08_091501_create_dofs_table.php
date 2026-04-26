<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dofs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('numero')->unique();
            $table->string('serie')->nullable();
            $table->decimal('saldo_inicial', 10, 4);
            $table->decimal('saldo_atual', 10, 4);
            $table->date('valido_ate');
            $table->string('status')->default('ATIVO'); // ATIVO, EXPIRADO, ZERADO
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dofs');
    }
};
