<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargo_permissao', function (Blueprint $table) {
            $table->foreignUuid('cargo_id')->constrained('cargos')->cascadeOnDelete();
            $table->foreignUuid('permissao_id')->constrained('permissoes')->cascadeOnDelete();
            $table->primary(['cargo_id', 'permissao_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargo_permissao');
    }
};
