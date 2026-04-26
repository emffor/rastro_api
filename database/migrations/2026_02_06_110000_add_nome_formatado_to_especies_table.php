<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('especies', function (Blueprint $table) {
            $table->string('nome_formatado')->nullable()->after('nome_tipo');
        });

        // Preenche o nome_formatado para registros existentes
        $especies = DB::table('especies')->get();

        foreach ($especies as $especie) {
            $parts = [];

            if ($especie->nome_tipo) {
                $parts[] = $especie->nome_tipo;
            }

            $nomesParts = [];
            if ($especie->nome_cientifico) {
                $nomesParts[] = $especie->nome_cientifico;
            }
            if ($especie->nome_popular) {
                $nomesParts[] = $especie->nome_popular;
            }

            if (count($nomesParts) > 0) {
                $parts[] = implode(' - ', $nomesParts);
            }

            $nomeFormatado = implode(' / ', $parts) ?: $especie->nome_popular;

            DB::table('especies')
                ->where('id', $especie->id)
                ->update(['nome_formatado' => $nomeFormatado]);
        }
    }

    public function down(): void
    {
        Schema::table('especies', function (Blueprint $table) {
            $table->dropColumn('nome_formatado');
        });
    }
};
