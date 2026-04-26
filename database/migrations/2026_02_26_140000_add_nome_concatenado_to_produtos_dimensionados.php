<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos_dimensionados', function (Blueprint $table) {
            $table->string('nome_concatenado', 255)->nullable()->after('nome');
        });

        $this->backfillNomeConcatenado();
    }

    public function down(): void
    {
        Schema::table('produtos_dimensionados', function (Blueprint $table) {
            $table->dropColumn('nome_concatenado');
        });
    }

    private function backfillNomeConcatenado(): void
    {
        if (!Schema::hasTable('produtos_dimensionados') || !Schema::hasTable('especies')) {
            return;
        }

        $produtos = DB::table('produtos_dimensionados as p')
            ->leftJoin('especies as e', 'e.id', '=', 'p.especie_id')
            ->select([
                'p.id',
                'p.nome',
                'p.tipo_dof',
                'p.espessura_cm',
                'p.largura_cm',
                'p.comprimento_m',
                'e.nome_popular',
            ])
            ->get();

        foreach ($produtos as $produto) {
            $nomeConcatenado = $this->formatarNomeConcatenado(
                tipo: (string) ($produto->tipo_dof ?? ''),
                nomePopular: (string) ($produto->nome_popular ?? ''),
                espessuraCm: (float) $produto->espessura_cm,
                larguraCm: (float) $produto->largura_cm,
                comprimentoM: (float) $produto->comprimento_m,
                fallbackNome: (string) ($produto->nome ?? ''),
            );

            DB::table('produtos_dimensionados')
                ->where('id', $produto->id)
                ->update(['nome_concatenado' => $nomeConcatenado]);
        }
    }

    private function formatarNomeConcatenado(
        string $tipo,
        string $nomePopular,
        float $espessuraCm,
        float $larguraCm,
        float $comprimentoM,
        string $fallbackNome = '',
    ): string {
        $tipoNormalizado = strtoupper(trim($tipo));
        $popular = trim(preg_replace('/\s+/u', ' ', $nomePopular)) ?: 'SEM_NOME_POPULAR';
        $popularUpper = mb_strtoupper($popular);

        $concatenado = sprintf(
            '%s %s %s(CM) x %s(CM) x %s(ML)',
            $tipoNormalizado,
            $popularUpper,
            number_format($espessuraCm, 2, '.', ''),
            number_format($larguraCm, 2, '.', ''),
            number_format($comprimentoM, 2, '.', ''),
        );

        $concatenado = trim($concatenado);

        if ($concatenado === '') {
            return trim($fallbackNome) !== '' ? trim($fallbackNome) : 'PRODUTO_DIMENSIONADO';
        }

        return mb_substr($concatenado, 0, 255);
    }
};

