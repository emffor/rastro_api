<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->foreignUuid('categoria_id')->nullable()->constrained('categorias');
        });

        Schema::table('dof_itens', function (Blueprint $table) {
            $table->foreignUuid('categoria_id')->nullable()->constrained('categorias');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropForeign(['categoria_id']);
            $table->dropColumn('categoria_id');
        });

        Schema::table('dof_itens', function (Blueprint $table) {
            $table->dropForeign(['categoria_id']);
            $table->dropColumn('categoria_id');
        });
    }
};
