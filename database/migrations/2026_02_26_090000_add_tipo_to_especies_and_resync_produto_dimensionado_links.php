<?php

use App\Support\ProdutoDimensionadoEspecieMatcher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('especies', 'tipo')) {
            Schema::table('especies', function (Blueprint $table) {
                $table->string('tipo', 20)->default('')->after('nome_popular');
            });
        }

        $this->backfillTipoCanonicoEspecies();

        if (Schema::hasColumn('especies', 'empresa_id')) {
            Schema::table('especies', function (Blueprint $table) {
                $table->index(['empresa_id', 'nome_popular', 'tipo'], 'idx_especies_empresa_popular_tipo');
            });
        }

        $this->resincronizarPivotProdutoDimensionadoEspecies();
    }

    public function down(): void
    {
        $temEmpresaId = Schema::hasColumn('especies', 'empresa_id');

        if (Schema::hasColumn('especies', 'tipo')) {
            Schema::table('especies', function (Blueprint $table) use ($temEmpresaId) {
                if ($temEmpresaId) {
                    $table->dropIndex('idx_especies_empresa_popular_tipo');
                }
                $table->dropColumn('tipo');
            });
        }
    }

    private function backfillTipoCanonicoEspecies(): void
    {
        DB::table('especies')
            ->select(['id', 'tipo', 'nome_tipo'])
            ->orderBy('id')
            ->chunk(500, function ($especies): void {
                foreach ($especies as $especie) {
                    $tipoCanonico = ProdutoDimensionadoEspecieMatcher::normalizarTipoEspecie(
                        $especie->tipo,
                        $especie->nome_tipo
                    );

                    DB::table('especies')
                        ->where('id', $especie->id)
                        ->update(['tipo' => $tipoCanonico]);
                }
            });
    }

    private function resincronizarPivotProdutoDimensionadoEspecies(): void
    {
        if (!Schema::hasTable('produtos_dimensionados') || !Schema::hasTable('produto_dimensionado_especies')) {
            return;
        }

        DB::table('produto_dimensionado_especies')->delete();

        $produtos = DB::table('produtos_dimensionados')
            ->select(['id', 'empresa_id', 'especie_id', 'tipo_dof'])
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->get();

        if ($produtos->isEmpty()) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($produtos as $produto) {
            $especieIds = $this->resolverEspeciesVinculadasParaProduto(
                empresaId: (string) $produto->empresa_id,
                especieBaseId: (string) $produto->especie_id,
                tipoDof: $produto->tipo_dof,
            );

            foreach ($especieIds as $especieId) {
                $rows[] = [
                    'produto_dimensionado_id' => (string) $produto->id,
                    'especie_id' => (string) $especieId,
                    'empresa_id' => (string) $produto->empresa_id,
                    'origem_vinculo' => 'AUTO',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('produto_dimensionado_especies')->insert($chunk);
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolverEspeciesVinculadasParaProduto(
        string $empresaId,
        string $especieBaseId,
        ?string $tipoDof,
    ): array {
        $especieBase = DB::table('especies')
            ->select(['id', 'nome_popular', 'tipo', 'nome_tipo'])
            ->where('id', $especieBaseId)
            ->where('empresa_id', $empresaId)
            ->whereNull('deleted_at')
            ->first();

        if (!$especieBase) {
            return [$especieBaseId];
        }

        $tipoProduto = ProdutoDimensionadoEspecieMatcher::normalizarTipo($tipoDof);
        if ($tipoProduto === 'SEM_TIPO') {
            return [$especieBaseId];
        }

        $nomePopularBase = ProdutoDimensionadoEspecieMatcher::normalizarTexto((string) $especieBase->nome_popular);
        if ($nomePopularBase === '') {
            return [$especieBaseId];
        }

        $especiesEmpresa = DB::table('especies')
            ->select(['id', 'nome_popular', 'tipo', 'nome_tipo'])
            ->where('empresa_id', $empresaId)
            ->whereNull('deleted_at')
            ->get();

        $ids = [];
        foreach ($especiesEmpresa as $especie) {
            $mesmoPopular = ProdutoDimensionadoEspecieMatcher::normalizarTexto((string) $especie->nome_popular) === $nomePopularBase;
            if (!$mesmoPopular) {
                continue;
            }

            $tipoEspecie = ProdutoDimensionadoEspecieMatcher::normalizarTipoEspecie(
                $especie->tipo,
                $especie->nome_tipo
            );
            if ($tipoEspecie !== $tipoProduto) {
                continue;
            }

            $ids[] = (string) $especie->id;
        }

        $ids[] = $especieBaseId;
        $ids = array_values(array_unique($ids));

        return !empty($ids) ? $ids : [$especieBaseId];
    }
};
