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
        Schema::table('pedidos', function (Blueprint $table) {
            $table->text('motivo_cancelamento')->nullable()->after('observacao');
            $table->foreignUuid('usuario_cancelamento_id')->nullable()->after('motivo_cancelamento')->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropForeign(['usuario_cancelamento_id']);
            $table->dropColumn(['motivo_cancelamento', 'usuario_cancelamento_id']);
        });
    }
};
