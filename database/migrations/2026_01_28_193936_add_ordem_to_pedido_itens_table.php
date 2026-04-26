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
        Schema::table('pedido_itens', function (Blueprint $table) {
            $table->integer('ordem')->default(0)->after('dof_debitos');
            $table->index(['pedido_id', 'ordem']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedido_itens', function (Blueprint $table) {
            $table->dropIndex(['pedido_id', 'ordem']);
            $table->dropColumn('ordem');
        });
    }
};
