<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->foreignUuid('lote_id')
                ->nullable()
                ->after('empresa_id')
                ->constrained('lotes')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->dropForeign(['lote_id']);
            $table->dropColumn('lote_id');
        });
    }
};
