<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->dateTime('data_movimentacao')->change();
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->date('data_movimentacao')->change();
        });
    }
};
