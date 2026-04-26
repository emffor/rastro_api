<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->dropForeign(['produto_id']);
            $table->foreignUuid('produto_id')->nullable()->change();
            $table->foreign('produto_id')->references('id')->on('produtos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->dropForeign(['produto_id']);
            $table->foreignUuid('produto_id')->nullable(false)->change();
            $table->foreign('produto_id')->references('id')->on('produtos');
        });
    }
};
