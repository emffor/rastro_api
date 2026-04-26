<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->string('origem')->default('MANUAL')->after('tipo');
            $table->string('referencia_tipo')->nullable()->after('origem');
            $table->uuid('referencia_id')->nullable()->after('referencia_tipo');
            $table->decimal('estoque_anterior', 10, 4)->nullable()->after('quantidade');
            $table->decimal('estoque_posterior', 10, 4)->nullable()->after('estoque_anterior');

            $table->index('origem');
            $table->index(['referencia_tipo', 'referencia_id']);
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->dropIndex(['origem']);
            $table->dropIndex(['referencia_tipo', 'referencia_id']);
            $table->dropColumn(['origem', 'referencia_tipo', 'referencia_id', 'estoque_anterior', 'estoque_posterior']);
        });
    }
};
