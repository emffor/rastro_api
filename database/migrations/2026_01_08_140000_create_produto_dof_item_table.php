<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produto_dof_item', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('produto_id')->constrained('produtos')->onDelete('cascade');
            $table->foreignUuid('dof_item_id')->constrained('dof_itens')->onDelete('cascade');
            $table->decimal('volume_alocado', 12, 6); // Volume em m³ alocado deste DOF
            $table->timestamps();

            $table->unique(['produto_id', 'dof_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_dof_item');
    }
};
