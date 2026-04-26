<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produtos_dimensionados', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignUuid('especie_id')->constrained('especies')->cascadeOnDelete();
            $table->string('tipo_dof', 50)->nullable();
            $table->string('nome', 160);
            $table->decimal('espessura_cm', 8, 2);
            $table->decimal('largura_cm', 8, 2);
            $table->decimal('comprimento_m', 8, 2);
            $table->decimal('volume_unitario_m3', 12, 6);
            $table->string('observacao', 255)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'ativo', 'nome'], 'idx_prod_dim_empresa_ativo_nome');
            $table->index(['empresa_id', 'especie_id', 'tipo_dof', 'ativo'], 'idx_prod_dim_empresa_especie_tipo');
        });

        Schema::create('dof_alocacoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignUuid('dof_id')->constrained('dofs')->cascadeOnDelete();
            $table->foreignUuid('dof_item_id')->nullable()->constrained('dof_itens')->nullOnDelete();
            $table->foreignUuid('lote_id')->constrained('lotes')->cascadeOnDelete();
            $table->foreignUuid('dof_lote_id')->nullable()->constrained('dof_lotes')->nullOnDelete();
            $table->string('modo_alocacao', 16);
            $table->decimal('volume_total_m3', 12, 4);
            $table->unsignedInteger('total_pecas')->default(0);
            $table->string('observacao', 500)->nullable();
            $table->foreignUuid('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['empresa_id', 'dof_id', 'created_at'], 'idx_dof_aloc_empresa_dof_created');
            $table->index(['empresa_id', 'dof_item_id', 'created_at'], 'idx_dof_aloc_empresa_dof_item_created');
            $table->index(['empresa_id', 'modo_alocacao'], 'idx_dof_aloc_empresa_modo');
        });

        Schema::create('dof_alocacao_linhas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dof_alocacao_id')->constrained('dof_alocacoes')->cascadeOnDelete();
            $table->foreignUuid('produto_dimensionado_id')->nullable()->constrained('produtos_dimensionados')->nullOnDelete();
            $table->unsignedSmallInteger('ordem');
            $table->unsignedInteger('quantidade_pecas');
            $table->decimal('volume_unitario_m3', 12, 6);
            $table->decimal('volume_total_m3', 12, 4);
            $table->string('produto_nome_snapshot', 160);
            $table->uuid('especie_id_snapshot');
            $table->string('tipo_dof_snapshot', 50);
            $table->decimal('espessura_cm_snapshot', 8, 2);
            $table->decimal('largura_cm_snapshot', 8, 2);
            $table->decimal('comprimento_m_snapshot', 8, 2);
            $table->timestamps();

            $table->index('dof_alocacao_id', 'idx_dof_aloc_linhas_alocacao');
            $table->index('produto_dimensionado_id', 'idx_dof_aloc_linhas_produto');
            $table->unique(['dof_alocacao_id', 'ordem'], 'uq_dof_aloc_linhas_ordem');
        });

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(
                "CREATE UNIQUE INDEX uq_prod_dim_empresa_dimensoes_ativas
                ON produtos_dimensionados (
                    empresa_id,
                    especie_id,
                    COALESCE(tipo_dof, ''),
                    espessura_cm,
                    largura_cm,
                    comprimento_m
                )
                WHERE deleted_at IS NULL"
            );

            DB::statement(
                'CREATE UNIQUE INDEX uq_dof_alocacoes_dof_lote_id
                 ON dof_alocacoes (dof_lote_id)
                 WHERE dof_lote_id IS NOT NULL'
            );
        } else {
            Schema::table('produtos_dimensionados', function (Blueprint $table) {
                $table->unique(
                    ['empresa_id', 'especie_id', 'tipo_dof', 'espessura_cm', 'largura_cm', 'comprimento_m', 'deleted_at'],
                    'uq_prod_dim_empresa_dimensoes_ativas'
                );
            });

            Schema::table('dof_alocacoes', function (Blueprint $table) {
                $table->unique('dof_lote_id', 'uq_dof_alocacoes_dof_lote_id');
            });
        }

        $this->backfillAlocacoesManuais();
    }

    private function backfillAlocacoesManuais(): void
    {
        $existsDofLotes = Schema::hasTable('dof_lotes');
        if (!$existsDofLotes) {
            return;
        }

        $dofLotes = DB::table('dof_lotes')
            ->select([
                'id',
                'empresa_id',
                'dof_id',
                'dof_item_id',
                'lote_id',
                'volume_m3',
                'observacao',
                'created_at',
                'updated_at',
            ])
            ->get();

        if ($dofLotes->isEmpty()) {
            return;
        }

        $rows = [];
        foreach ($dofLotes as $dofLote) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'empresa_id' => $dofLote->empresa_id,
                'dof_id' => $dofLote->dof_id,
                'dof_item_id' => $dofLote->dof_item_id,
                'lote_id' => $dofLote->lote_id,
                'dof_lote_id' => $dofLote->id,
                'modo_alocacao' => 'MANUAL',
                'volume_total_m3' => $dofLote->volume_m3,
                'total_pecas' => 0,
                'observacao' => $dofLote->observacao,
                'usuario_id' => null,
                'created_at' => $dofLote->created_at ?? now(),
                'updated_at' => $dofLote->updated_at ?? now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('dof_alocacoes')->insert($chunk);
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS uq_dof_alocacoes_dof_lote_id');
            DB::statement('DROP INDEX IF EXISTS uq_prod_dim_empresa_dimensoes_ativas');
        }

        Schema::dropIfExists('dof_alocacao_linhas');
        Schema::dropIfExists('dof_alocacoes');
        Schema::dropIfExists('produtos_dimensionados');
    }
};
