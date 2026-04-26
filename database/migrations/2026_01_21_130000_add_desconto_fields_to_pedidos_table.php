<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->decimal('desconto', 10, 2)->default(0)->after('valor_total');
            $table->decimal('valor_final', 10, 2)->nullable()->after('desconto');
        });

        // Atualiza valor_final para ser igual ao valor_total nos registros existentes
        DB::statement('UPDATE pedidos SET valor_final = valor_total');

        // Opcional: Tornar não nulo após popular
        Schema::table('pedidos', function (Blueprint $table) {
             $table->decimal('valor_final', 10, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn(['desconto', 'valor_final']);
        });
    }
};
