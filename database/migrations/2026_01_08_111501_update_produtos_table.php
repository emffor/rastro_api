<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            // Remover FK antigas se existirem
            $table->dropForeign(['dof_id']);
            $table->dropForeign(['especie_id']);
            $table->dropColumn(['dof_id', 'especie_id']);
            
            // Novo relacionamento com dof_itens
            $table->foreignUuid('dof_item_id')->nullable()->after('tipo')->constrained('dof_itens')->nullOnDelete();
            
            // Dimensões para cálculo de volume
            $table->decimal('largura', 8, 2)->nullable()->after('unidade'); // cm
            $table->decimal('espessura', 8, 2)->nullable()->after('largura'); // cm
            $table->decimal('comprimento', 8, 2)->nullable()->after('espessura'); // metros
            $table->decimal('volume_unitario', 10, 6)->nullable()->after('comprimento'); // M³ calculado
            
            // Estoque físico
            $table->decimal('estoque_quantidade', 10, 4)->default(0)->after('volume_unitario');
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropForeign(['dof_item_id']);
            $table->dropColumn(['dof_item_id', 'largura', 'espessura', 'comprimento', 'volume_unitario', 'estoque_quantidade']);
            
            $table->foreignUuid('dof_id')->nullable()->constrained('dofs')->nullOnDelete();
            $table->foreignUuid('especie_id')->nullable()->constrained('especies')->nullOnDelete();
        });
    }
};
