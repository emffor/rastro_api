<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('logo_url')->nullable()->after('telefone');
            $table->string('endereco')->nullable()->after('logo_url');
            $table->string('cidade')->nullable()->after('endereco');
            $table->string('estado', 2)->nullable()->after('cidade');
            $table->string('cep', 10)->nullable()->after('estado');
            $table->string('inscricao_estadual')->nullable()->after('cep');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn([
                'logo_url',
                'endereco',
                'cidade',
                'estado',
                'cep',
                'inscricao_estadual',
            ]);
        });
    }
};
