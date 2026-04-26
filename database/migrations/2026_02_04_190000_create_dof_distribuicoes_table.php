<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dof_distribuicoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dof_item_id')->constrained('dof_itens')->onDelete('cascade');
            $table->foreignUuid('produto_id')->constrained('produtos')->onDelete('cascade');
            $table->integer('quantidade')->default(0);
            $table->decimal('comprimento', 8, 2)->nullable();
            $table->decimal('volume_unitario', 12, 6)->default(0);
            $table->decimal('volume_total', 12, 6)->default(0);
            $table->timestamps();

            $table->unique(['dof_item_id', 'produto_id', 'comprimento'], 'dof_dist_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dof_distribuicoes');
    }
};
