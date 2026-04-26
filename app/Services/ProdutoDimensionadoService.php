<?php

namespace App\Services;

use App\Models\Especie;
use App\Models\ProdutoDimensionado;
use App\Support\ProdutoDimensionadoEspecieMatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProdutoDimensionadoService
{
    private function aplicarFiltros(Builder $query, array $filtros = []): Builder
    {
        if (!empty($filtros['busca'])) {
            $busca = trim((string) $filtros['busca']);
            if ($busca !== '') {
                $query->where(function (Builder $subQuery) use ($busca) {
                    $subQuery
                        ->where('produtos_dimensionados.codigo', 'LIKE', "%{$busca}%")
                        ->orWhere('produtos_dimensionados.nome', 'LIKE', "%{$busca}%")
                        ->orWhere('produtos_dimensionados.nome_concatenado', 'LIKE', "%{$busca}%")
                        ->orWhere('produtos_dimensionados.tipo_dof', 'LIKE', "%{$busca}%")
                        ->orWhere('produtos_dimensionados.espessura_cm', 'LIKE', "%{$busca}%")
                        ->orWhere('produtos_dimensionados.largura_cm', 'LIKE', "%{$busca}%")
                        ->orWhere('produtos_dimensionados.comprimento_m', 'LIKE', "%{$busca}%")
                        ->orWhere('especie_base.nome_popular', 'LIKE', "%{$busca}%")
                        ->orWhere('especie_base.nome_cientifico', 'LIKE', "%{$busca}%")
                        ->orWhere('especie_base.nome_formatado', 'LIKE', "%{$busca}%");
                });
            }
        }

        if (!empty($filtros['especie_id'])) {
            $especieId = (string) $filtros['especie_id'];
            $query->where(function (Builder $subQuery) use ($especieId) {
                $subQuery
                    ->where('especie_id', $especieId)
                    ->orWhereHas('especiesVinculadas', function (Builder $vinculadas) use ($especieId) {
                        $vinculadas->where('especies.id', $especieId);
                    });
            });
        }

        if (!empty($filtros['tipo_dof'])) {
            $query->where('tipo_dof', ProdutoDimensionado::normalizarTipo($filtros['tipo_dof']));
        }

        if (isset($filtros['ativo']) && $filtros['ativo'] !== '') {
            $ativo = filter_var($filtros['ativo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($ativo !== null) {
                $query->where('ativo', $ativo);
            }
        }

        return $query;
    }

    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        $incluirEspeciesVinculadas = $this->deveIncluirEspeciesVinculadas($filtros);

        $query = ProdutoDimensionado::query()
            ->leftJoin('especies as especie_base', 'especie_base.id', '=', 'produtos_dimensionados.especie_id')
            ->select('produtos_dimensionados.*')
            ->with('especie.tipoSerragem')
            ->withCount('especiesVinculadas');

        $this->aplicarOrdenacaoListagem($query);

        if ($incluirEspeciesVinculadas) {
            $query->with('especiesVinculadas.tipoSerragem');
        }

        $this->aplicarFiltros($query, $filtros);

        if (($filtros['all'] ?? null) === 'true') {
            $items = $query->get();
            return new LengthAwarePaginator(
                $items,
                $items->count(),
                $items->count() ?: 1,
                1
            );
        }

        return $query->paginate($perPage);
    }

    private function aplicarOrdenacaoListagem(Builder $query): void
    {
        $query
            ->orderByRaw("LOWER(COALESCE(especie_base.nome_popular, ''))")
            ->orderByRaw("LOWER(COALESCE(especie_base.nome_cientifico, ''))")
            ->orderBy('produtos_dimensionados.tipo_dof')
            ->orderBy('produtos_dimensionados.espessura_cm')
            ->orderBy('produtos_dimensionados.largura_cm')
            ->orderBy('produtos_dimensionados.comprimento_m')
            ->orderBy('produtos_dimensionados.nome');
    }

    public function buscarPorId(string $id): ProdutoDimensionado
    {
        return ProdutoDimensionado::query()
            ->with(['especie.tipoSerragem', 'especiesVinculadas.tipoSerragem'])
            ->withCount('especiesVinculadas')
            ->findOrFail($id);
    }

    public function criar(array $dados): ProdutoDimensionado
    {
        try {
            return DB::transaction(function () use ($dados) {
                $tipoNormalizado = $this->resolverTipoProduto($dados);
                $especieBase = array_key_exists('especie_id', $dados) && !empty($dados['especie_id'])
                    ? $this->resolverEspecieBase((string) $dados['especie_id'])
                    : $this->resolverEspecieBasePorTipoENomePopular(
                        $tipoNormalizado,
                        (string) ($dados['nome_popular'] ?? '')
                    );
                $ativo = array_key_exists('ativo', $dados) ? (bool) $dados['ativo'] : true;
                $espessuraCm = (float) $dados['espessura_cm'];
                $larguraCm = (float) $dados['largura_cm'];
                $comprimentoM = (float) $dados['comprimento_m'];
                $nomeProduto = $this->resolverNomeProduto(
                    tipo: $tipoNormalizado,
                    nomePopular: (string) $especieBase->nome_popular,
                    espessuraCm: $espessuraCm,
                    larguraCm: $larguraCm,
                    comprimentoM: $comprimentoM,
                );

                if ($ativo) {
                    $this->validarDuplicidadeDimensionalPorGrupo(
                        empresaId: (string) $especieBase->empresa_id,
                        especieBase: $especieBase,
                        tipoDof: $tipoNormalizado,
                        espessuraCm: $espessuraCm,
                        larguraCm: $larguraCm,
                        comprimentoM: $comprimentoM,
                    );
                }

                $produto = ProdutoDimensionado::create([
                    'especie_id' => $especieBase->id,
                    'tipo_dof' => $tipoNormalizado,
                    'nome' => $nomeProduto,
                    'nome_concatenado' => $nomeProduto,
                    'espessura_cm' => $espessuraCm,
                    'largura_cm' => $larguraCm,
                    'comprimento_m' => $comprimentoM,
                    'observacao' => $dados['observacao'] ?? null,
                    'ativo' => $ativo,
                ]);

                $this->sincronizarEspeciesVinculadas($produto, $especieBase, $tipoNormalizado);

                return $produto->fresh(['especie.tipoSerragem', 'especiesVinculadas.tipoSerragem'])
                    ->loadCount('especiesVinculadas');
            });
        } catch (\Throwable $e) {
            Log::error('Erro ao criar produto dimensionado', [
                'dados' => $dados,
                'erro' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function atualizar(string $id, array $dados): ProdutoDimensionado
    {
        try {
            return DB::transaction(function () use ($id, $dados) {
                $produto = ProdutoDimensionado::query()->with('especie.tipoSerragem')->findOrFail($id);

                $especieBaseAtual = $produto->especie;
                if (!$especieBaseAtual) {
                    throw new \DomainException('Espécie base atual do produto não encontrada.');
                }

                $especieBaseNova = array_key_exists('especie_id', $dados)
                    ? $this->resolverEspecieBase((string) $dados['especie_id'])
                    : $especieBaseAtual;

                $tipoAtual = ProdutoDimensionado::normalizarTipo($produto->tipo_dof);
                $tipoNovo = $this->resolverTipoProduto($dados, $tipoAtual);

                $nomePopularAtual = trim((string) $especieBaseAtual->nome_popular);
                $nomePopularNovo = array_key_exists('nome_popular', $dados)
                    ? trim((string) $dados['nome_popular'])
                    : $nomePopularAtual;

                $seletoresAutomaticosInformados = array_key_exists('tipo_especie', $dados)
                    || array_key_exists('tipo_dof', $dados)
                    || array_key_exists('nome_popular', $dados);

                if (!array_key_exists('especie_id', $dados) && $seletoresAutomaticosInformados) {
                    $especieBaseNova = $this->resolverEspecieBasePorTipoENomePopular(
                        $tipoNovo,
                        $nomePopularNovo,
                        (string) $especieBaseAtual->id,
                    );
                }

                $espessuraNova = array_key_exists('espessura_cm', $dados)
                    ? (float) $dados['espessura_cm']
                    : (float) $produto->espessura_cm;
                $larguraNova = array_key_exists('largura_cm', $dados)
                    ? (float) $dados['largura_cm']
                    : (float) $produto->largura_cm;
                $comprimentoNovo = array_key_exists('comprimento_m', $dados)
                    ? (float) $dados['comprimento_m']
                    : (float) $produto->comprimento_m;

                $mudouEspecieBase = (string) $especieBaseNova->id !== (string) $produto->especie_id;
                $mudouTipo = $tipoNovo !== $tipoAtual;
                $mudouEspessura = $espessuraNova !== (float) $produto->espessura_cm;
                $mudouLargura = $larguraNova !== (float) $produto->largura_cm;
                $mudouComprimento = $comprimentoNovo !== (float) $produto->comprimento_m;
                $mudouEstrutural = $mudouEspecieBase || $mudouTipo || $mudouEspessura || $mudouLargura || $mudouComprimento;
                $nomeNovo = $this->resolverNomeProduto(
                    tipo: $tipoNovo,
                    nomePopular: (string) $especieBaseNova->nome_popular,
                    espessuraCm: $espessuraNova,
                    larguraCm: $larguraNova,
                    comprimentoM: $comprimentoNovo,
                );

                $jaFoiUtilizado = $produto->alocacaoLinhas()->exists();
                if ($jaFoiUtilizado && $mudouEstrutural) {
                    throw new \DomainException('Não é possível alterar dimensões, espécie base ou tipo de um produto dimensionado já utilizado em alocações.');
                }

                $ativoAtual = (bool) $produto->ativo;
                $ativoNovo = array_key_exists('ativo', $dados) ? (bool) $dados['ativo'] : $ativoAtual;
                $ativandoProduto = !$ativoAtual && $ativoNovo;

                if ($ativoNovo && ($mudouEstrutural || $ativandoProduto)) {
                    $this->validarDuplicidadeDimensionalPorGrupo(
                        empresaId: (string) $especieBaseNova->empresa_id,
                        especieBase: $especieBaseNova,
                        tipoDof: $tipoNovo,
                        espessuraCm: $espessuraNova,
                        larguraCm: $larguraNova,
                        comprimentoM: $comprimentoNovo,
                        ignorarProdutoId: $produto->id,
                    );
                }

                $produto->fill($dados);
                $produto->especie_id = $especieBaseNova->id;
                $produto->tipo_dof = $tipoNovo;
                $produto->nome = $nomeNovo;
                $produto->nome_concatenado = $nomeNovo;
                $produto->save();

                $this->sincronizarEspeciesVinculadas($produto, $especieBaseNova, $tipoNovo);

                return $produto->fresh(['especie.tipoSerragem', 'especiesVinculadas.tipoSerragem'])
                    ->loadCount('especiesVinculadas');
            });
        } catch (\Throwable $e) {
            Log::error('Erro ao atualizar produto dimensionado', [
                'id' => $id,
                'dados' => $dados,
                'erro' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function excluir(string $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $produto = ProdutoDimensionado::findOrFail($id);

                if ($produto->alocacaoLinhas()->exists()) {
                    throw new \DomainException('Não é possível excluir um produto dimensionado que já foi utilizado em alocações.');
                }

                $produto->delete();
            });
        } catch (\Throwable $e) {
            Log::error('Erro ao excluir produto dimensionado', [
                'id' => $id,
                'erro' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function ressincronizarVinculosPorEspecie(
        Especie $especie,
        ?string $nomePopularAnterior = null,
        ?string $tipoAnterior = null,
    ): void {
        $popularAtual = ProdutoDimensionado::normalizarTexto($especie->nome_popular);
        $popularAnteriorNormalizado = ProdutoDimensionado::normalizarTexto($nomePopularAnterior);

        $popularesAlvo = array_values(array_filter(array_unique([
            $popularAtual,
            $popularAnteriorNormalizado,
        ])));

        $tipoAtual = $this->resolverTipoCanonicoDaEspecie($especie);
        $tipoAnteriorNormalizado = ProdutoDimensionado::normalizarTipo($tipoAnterior);
        $tiposAlvo = array_values(array_filter(array_unique([
            $tipoAtual,
            $tipoAnteriorNormalizado,
        ])));

        DB::transaction(function () use ($especie, $popularesAlvo, $tiposAlvo) {
            $candidatos = ProdutoDimensionado::query()
                ->with([
                    'especie:id,empresa_id,nome_popular,tipo,nome_tipo,tipo_serragem_id',
                    'especie.tipoSerragem:id,nome',
                    'especiesVinculadas:id,tipo_serragem_id',
                    'especiesVinculadas.tipoSerragem:id,nome',
                ])
                ->where('empresa_id', $especie->empresa_id)
                ->get();

            foreach ($candidatos as $produto) {
                if (!$produto->especie) {
                    continue;
                }

                $tipoProduto = ProdutoDimensionado::normalizarTipo($produto->tipo_dof);
                if (!empty($tiposAlvo) && !in_array($tipoProduto, $tiposAlvo, true)) {
                    continue;
                }

                $popularBase = ProdutoDimensionado::normalizarTexto($produto->especie->nome_popular);
                $envolvePopular = !empty($popularesAlvo) && in_array($popularBase, $popularesAlvo, true);
                $envolveEspecie = (string) $produto->especie_id === (string) $especie->id
                    || $produto->especiesVinculadas->contains('id', $especie->id);

                if (!$envolvePopular && !$envolveEspecie) {
                    continue;
                }

                $this->sincronizarEspeciesVinculadas(
                    $produto,
                    $produto->especie,
                    ProdutoDimensionado::normalizarTipo($produto->tipo_dof),
                );
            }
        });
    }

    private function resolverEspecieBase(string $especieId): Especie
    {
        $especie = Especie::find($especieId);
        if (!$especie) {
            throw new \DomainException('Espécie inválida para a empresa atual.');
        }

        return $especie;
    }

    private function resolverEspecieBasePorTipoENomePopular(
        string $tipoDof,
        string $nomePopular,
        ?string $especiePreferencialId = null,
    ): Especie {
        $nomePopularNormalizado = ProdutoDimensionado::normalizarTexto($nomePopular);
        if ($nomePopularNormalizado === '') {
            throw new \DomainException('Nome popular é obrigatório para vinculação automática de espécies.');
        }

        $tipoNormalizado = ProdutoDimensionado::normalizarTipo($tipoDof);
        $empresaId = request()->get('empresa_id') ?: auth()->user()?->empresa_id;

        if (empty($empresaId)) {
            throw new \DomainException('Empresa inválida para vinculação automática de espécies.');
        }

        $candidatas = Especie::query()
            ->where('empresa_id', $empresaId)
            ->where(function (Builder $query) use ($tipoNormalizado) {
                $query
                    ->whereHas('tipoSerragem', function (Builder $tipoQuery) use ($tipoNormalizado) {
                        $tipoQuery->where('nome', $tipoNormalizado);
                    })
                    ->orWhere('tipo', $tipoNormalizado);
            })
            ->with('tipoSerragem:id,nome')
            ->orderBy('nome_cientifico')
            ->orderBy('id')
            ->get()
            ->filter(fn (Especie $especie): bool => ProdutoDimensionado::normalizarTexto($especie->nome_popular) === $nomePopularNormalizado)
            ->values();

        if ($candidatas->isEmpty()) {
            throw new \DomainException("Não foi encontrada espécie para o nome popular '{$nomePopular}' e tipo '{$tipoNormalizado}'.");
        }

        if ($especiePreferencialId) {
            $preferencial = $candidatas->firstWhere('id', $especiePreferencialId);
            if ($preferencial instanceof Especie) {
                return $preferencial;
            }
        }

        return $candidatas->first();
    }

    private function resolverTipoProduto(array $dados, ?string $fallback = null): string
    {
        if (array_key_exists('tipo_especie', $dados)) {
            return ProdutoDimensionado::normalizarTipo($dados['tipo_especie']);
        }

        if (array_key_exists('tipo_dof', $dados)) {
            return ProdutoDimensionado::normalizarTipo($dados['tipo_dof']);
        }

        return ProdutoDimensionado::normalizarTipo($fallback);
    }

    private function resolverNomeProduto(
        string $tipo,
        string $nomePopular,
        float $espessuraCm,
        float $larguraCm,
        float $comprimentoM,
    ): string {
        return $this->formatarNomeProdutoDimensionado($tipo, $nomePopular, $espessuraCm, $larguraCm, $comprimentoM);
    }

    private function formatarNomeProdutoDimensionado(
        string $tipo,
        string $nomePopular,
        float $espessuraCm,
        float $larguraCm,
        float $comprimentoM,
    ): string {
        $tipoNormalizado = ProdutoDimensionado::normalizarTipo($tipo);
        $nomePopularNormalizado = trim(preg_replace('/\s+/u', ' ', $nomePopular)) ?: 'SEM_NOME_POPULAR';

        return sprintf(
            '%s %s %s(CM) x %s(CM) x %s(ML)',
            $tipoNormalizado,
            mb_strtoupper($nomePopularNormalizado),
            number_format($espessuraCm, 2, '.', ''),
            number_format($larguraCm, 2, '.', ''),
            number_format($comprimentoM, 2, '.', ''),
        );
    }

    private function deveIncluirEspeciesVinculadas(array $filtros): bool
    {
        $with = trim((string) ($filtros['with'] ?? ''));
        if ($with === '') {
            return false;
        }

        $campos = array_filter(array_map('trim', explode(',', $with)));
        return in_array('especies_vinculadas', $campos, true);
    }

    /**
     * @return array<int, string>
     */
    private function resolverEspeciesVinculadasIds(Especie $especieBase, string $tipoDof): array
    {
        $nomePopularBase = ProdutoDimensionado::normalizarTexto($especieBase->nome_popular);
        $tipoNormalizado = ProdutoDimensionado::normalizarTipo($tipoDof);

        if ($nomePopularBase === '' || $tipoNormalizado === '') {
            return [(string) $especieBase->id];
        }

        $especies = Especie::query()
            ->select(['id', 'nome_popular', 'tipo', 'nome_tipo', 'tipo_serragem_id'])
            ->with('tipoSerragem:id,nome')
            ->where('empresa_id', $especieBase->empresa_id)
            ->get();

        $ids = [];
        foreach ($especies as $especie) {
            $mesmoPopular = ProdutoDimensionado::normalizarTexto($especie->nome_popular) === $nomePopularBase;
            if (!$mesmoPopular) {
                continue;
            }

            $tipoDaEspecie = $this->resolverTipoCanonicoDaEspecie($especie);
            if ($tipoDaEspecie !== $tipoNormalizado) {
                continue;
            }

            $ids[] = (string) $especie->id;
        }

        $ids[] = (string) $especieBase->id;
        $ids = array_values(array_unique($ids));

        return !empty($ids) ? $ids : [(string) $especieBase->id];
    }

    private function resolverTipoCanonicoDaEspecie(Especie $especie): string
    {
        return $especie->resolverTipoSerragemNome();
    }

    private function sincronizarEspeciesVinculadas(
        ProdutoDimensionado $produto,
        Especie $especieBase,
        string $tipoDof,
    ): void {
        $ids = $this->resolverEspeciesVinculadasIds($especieBase, $tipoDof);

        $syncPayload = [];
        foreach ($ids as $id) {
            $syncPayload[$id] = [
                'empresa_id' => $produto->empresa_id,
                'origem_vinculo' => 'AUTO',
            ];
        }

        $produto->especiesVinculadas()->sync($syncPayload);
    }

    private function validarDuplicidadeDimensionalPorGrupo(
        string $empresaId,
        Especie $especieBase,
        string $tipoDof,
        float $espessuraCm,
        float $larguraCm,
        float $comprimentoM,
        ?string $ignorarProdutoId = null,
    ): void {
        $especiesVinculadasNovoProduto = $this->resolverEspeciesVinculadasIds($especieBase, $tipoDof);

        $query = ProdutoDimensionado::query()
            ->with('especiesVinculadas:id')
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->where('tipo_dof', ProdutoDimensionado::normalizarTipo($tipoDof))
            ->where('espessura_cm', $espessuraCm)
            ->where('largura_cm', $larguraCm)
            ->where('comprimento_m', $comprimentoM);

        if ($ignorarProdutoId) {
            $query->where('id', '<>', $ignorarProdutoId);
        }

        $candidatos = $query->get(['id', 'especie_id']);
        foreach ($candidatos as $candidato) {
            $especiesCandidato = $candidato->especiesVinculadas
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->values()
                ->all();

            if (empty($especiesCandidato)) {
                $especiesCandidato = [(string) $candidato->especie_id];
            }

            $intersecao = array_intersect($especiesVinculadasNovoProduto, $especiesCandidato);
            if (!empty($intersecao)) {
                throw new \DomainException('Já existe produto dimensionado ativo com a mesma combinação de tipo, dimensões e grupo de espécies vinculadas.');
            }
        }
    }
}
