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
        Schema::create('produto_dimensionado_especies', function (Blueprint $table) {
            $table->uuid('produto_dimensionado_id');
            $table->foreign('produto_dimensionado_id', 'fk_pd_especies_produto')
                ->references('id')
                ->on('produtos_dimensionados')
                ->onDelete('cascade');

            $table->uuid('especie_id');
            $table->foreign('especie_id', 'fk_pd_especies_especie')
                ->references('id')
                ->on('especies')
                ->onDelete('cascade');

            $table->uuid('empresa_id');
            $table->foreign('empresa_id', 'fk_pd_especies_empresa')
                ->references('id')
                ->on('empresas')
                ->onDelete('cascade');

            $table->string('origem_vinculo', 16)->default('AUTO');
            $table->timestamps();

            $table->primary(['produto_dimensionado_id', 'especie_id'], 'pk_produto_dimensionado_especies');
            $table->index(['empresa_id', 'especie_id'], 'idx_pd_especies_especie');
            $table->index('produto_dimensionado_id', 'idx_pd_especies_produto');
        });

        $this->backfillProdutosDimensionados();
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_dimensionado_especies');
    }

    private function backfillProdutosDimensionados(): void
    {
        if (!Schema::hasTable('produtos_dimensionados') || !Schema::hasTable('especies')) {
            return;
        }

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
            DB::table('produto_dimensionado_especies')->upsert(
                $chunk,
                ['produto_dimensionado_id', 'especie_id'],
                ['updated_at']
            );
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
            ->select(['id', 'nome_popular', 'nome_tipo'])
            ->where('id', $especieBaseId)
            ->where('empresa_id', $empresaId)
            ->whereNull('deleted_at')
            ->first();

        if (!$especieBase) {
            return [$especieBaseId];
        }

        $nomePopularBase = ProdutoDimensionadoEspecieMatcher::normalizarTexto((string) $especieBase->nome_popular);
        $tipoProduto = ProdutoDimensionadoEspecieMatcher::normalizarTipo($tipoDof);

        $especiesEmpresa = DB::table('especies')
            ->select(['id', 'nome_popular', 'nome_tipo'])
            ->where('empresa_id', $empresaId)
            ->whereNull('deleted_at')
            ->get();

        $ids = [];
        foreach ($especiesEmpresa as $especie) {
            $mesmoPopular = ProdutoDimensionadoEspecieMatcher::normalizarTexto((string) $especie->nome_popular) === $nomePopularBase;
            if (!$mesmoPopular) {
                continue;
            }

            if ($tipoProduto !== 'SEM_TIPO') {
                $tipoDaEspecie = ProdutoDimensionadoEspecieMatcher::tipoDaEspecie($especie->nome_tipo);
                if ($tipoDaEspecie !== $tipoProduto) {
                    continue;
                }
            }

            $ids[] = (string) $especie->id;
        }

        $ids[] = $especieBaseId;
        $ids = array_values(array_unique($ids));

        return !empty($ids) ? $ids : [$especieBaseId];
    }
};
