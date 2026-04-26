<?php

use App\Support\ProdutoDimensionadoEspecieMatcher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('especies') || !Schema::hasColumn('especies', 'tipo')) {
            return;
        }

        DB::table('especies')
            ->select(['id', 'nome_cientifico', 'nome_popular', 'nome_tipo', 'tipo'])
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunk(500, function ($especies): void {
                foreach ($especies as $especie) {
                    $nomeTipoNormalizado = ProdutoDimensionadoEspecieMatcher::normalizarNomeTipoDescricao(
                        $especie->nome_tipo,
                        $especie->tipo
                    );

                    $nomeFormatado = $this->gerarNomeFormatado(
                        nomeTipo: $nomeTipoNormalizado,
                        nomeCientifico: (string) ($especie->nome_cientifico ?? ''),
                        nomePopular: (string) ($especie->nome_popular ?? '')
                    );

                    DB::table('especies')
                        ->where('id', $especie->id)
                        ->update([
                            'nome_tipo' => $nomeTipoNormalizado,
                            'nome_formatado' => $nomeFormatado,
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Sem rollback de conteúdo textual normalizado.
    }

    private function gerarNomeFormatado(
        string $nomeTipo,
        string $nomeCientifico,
        string $nomePopular,
    ): string {
        $parts = [];
        $nomeTipo = trim($nomeTipo);
        if ($nomeTipo !== '') {
            $parts[] = $nomeTipo;
        }

        $nomesParts = [];
        $nomeCientifico = trim($nomeCientifico);
        $nomePopular = trim($nomePopular);
        if ($nomeCientifico !== '') {
            $nomesParts[] = $nomeCientifico;
        }
        if ($nomePopular !== '') {
            $nomesParts[] = $nomePopular;
        }

        if (!empty($nomesParts)) {
            $parts[] = implode(' - ', $nomesParts);
        }

        return !empty($parts) ? implode(' / ', $parts) : $nomePopular;
    }
};
