<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_itens', function (Blueprint $table) {
            $table->json('dof_debitos')->nullable()->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_itens', function (Blueprint $table) {
            $table->dropColumn('dof_debitos');
        });
    }
};
