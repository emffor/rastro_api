<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dof_distribuicoes', function (Blueprint $table) {
            $table->foreignUuid('lote_id')
                ->nullable()
                ->after('produto_id')
                ->constrained('lotes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dof_distribuicoes', function (Blueprint $table) {
            $table->dropForeign(['lote_id']);
            $table->dropColumn('lote_id');
        });
    }
};
