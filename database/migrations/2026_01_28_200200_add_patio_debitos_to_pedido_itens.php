<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_itens', function (Blueprint $table) {
            $table->json('patio_debitos')->nullable()->after('dof_debitos');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_itens', function (Blueprint $table) {
            $table->dropColumn('patio_debitos');
        });
    }
};
