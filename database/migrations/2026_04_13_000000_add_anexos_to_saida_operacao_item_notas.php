<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saida_operacao_item_notas', function (Blueprint $table) {
            $table->string('anexo_nf_path')->nullable()->after('data_emissao_nf');
            $table->string('anexo_nf_url', 2048)->nullable()->after('anexo_nf_path');
            $table->string('anexo_dof_path')->nullable()->after('anexo_nf_url');
            $table->string('anexo_dof_url', 2048)->nullable()->after('anexo_dof_path');
            $table->string('anexo_nf_original_name')->nullable()->after('anexo_dof_url');
            $table->string('anexo_dof_original_name')->nullable()->after('anexo_nf_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('saida_operacao_item_notas', function (Blueprint $table) {
            $table->dropColumn([
                'anexo_nf_path',
                'anexo_nf_url',
                'anexo_dof_path',
                'anexo_dof_url',
                'anexo_nf_original_name',
                'anexo_dof_original_name',
            ]);
        });
    }
};
