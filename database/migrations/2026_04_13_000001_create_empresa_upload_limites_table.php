<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_upload_limites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('empresa_id');
            $table->string('mes_referencia', 7);
            $table->unsignedInteger('uploads_nf')->default(0);
            $table->unsignedInteger('uploads_dof')->default(0);
            $table->timestamps();

            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');
            $table->unique(['empresa_id', 'mes_referencia']);
            $table->index(['empresa_id', 'mes_referencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_upload_limites');
    }
};
