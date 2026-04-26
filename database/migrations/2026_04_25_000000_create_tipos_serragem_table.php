<?php

use App\Models\TipoSerragem;
use App\Support\ProdutoDimensionadoEspecieMatcher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_serragem', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome', 50);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['empresa_id', 'nome'], 'tipos_serragem_empresa_nome_unique');
        });

        Schema::table('especies', function (Blueprint $table) {
            $table->foreignUuid('tipo_serragem_id')
                ->nullable()
                ->after('empresa_id')
                ->constrained('tipos_serragem')
                ->nullOnDelete();
            $table->index(['empresa_id', 'tipo_serragem_id'], 'idx_especies_empresa_tipo_serragem');
        });

        $this->backfillTiposSerragem();
    }

    public function down(): void
    {
        Schema::table('especies', function (Blueprint $table) {
            $table->dropForeign(['tipo_serragem_id']);
            $table->dropIndex('idx_especies_empresa_tipo_serragem');
            $table->dropColumn('tipo_serragem_id');
        });

        Schema::dropIfExists('tipos_serragem');
    }

    private function backfillTiposSerragem(): void
    {
        $empresasIds = DB::table('empresas')->pluck('id');
        $now = now();

        foreach ($empresasIds as $empresaId) {
            foreach (TipoSerragem::TIPOS_PADRAO as $nome) {
                $this->obterOuCriarTipo((string) $empresaId, $nome, $now);
            }
        }

        DB::table('especies')
            ->select(['id', 'empresa_id', 'tipo', 'nome_tipo'])
            ->whereNotNull('empresa_id')
            ->orderBy('id')
            ->chunk(500, function ($especies) use ($now): void {
                foreach ($especies as $especie) {
                    $tipo = ProdutoDimensionadoEspecieMatcher::normalizarTipoEspecie(
                        $especie->tipo,
                        $especie->nome_tipo,
                    );

                    if ($tipo === '') {
                        continue;
                    }

                    $tipoId = $this->obterOuCriarTipo((string) $especie->empresa_id, $tipo, $now);
                    $nomeTipo = ProdutoDimensionadoEspecieMatcher::normalizarNomeTipoDescricao(
                        $especie->nome_tipo,
                        $tipo,
                    );

                    DB::table('especies')
                        ->where('id', $especie->id)
                        ->update([
                            'tipo_serragem_id' => $tipoId,
                            'tipo' => $tipo,
                            'nome_tipo' => $nomeTipo,
                            'updated_at' => $now,
                        ]);
                }
            });
    }

    private function obterOuCriarTipo(string $empresaId, string $nome, mixed $now): string
    {
        $nomeNormalizado = ProdutoDimensionadoEspecieMatcher::normalizarTipo($nome);
        $tipoExistente = DB::table('tipos_serragem')
            ->where('empresa_id', $empresaId)
            ->where('nome', $nomeNormalizado)
            ->first();

        if ($tipoExistente) {
            return (string) $tipoExistente->id;
        }

        $id = (string) Str::uuid();

        DB::table('tipos_serragem')->insert([
            'id' => $id,
            'empresa_id' => $empresaId,
            'nome' => $nomeNormalizado,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }
};
