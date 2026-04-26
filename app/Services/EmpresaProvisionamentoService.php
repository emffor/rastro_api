<?php

namespace App\Services;

use App\Models\Cargo;
use App\Models\Empresa;
use App\Models\Especie;
use App\Models\Permissao;
use App\Models\ProdutoDimensionado;
use App\Services\TipoSerragemService;
use App\Support\ProdutoDimensionadoEspecieMatcher;
use Database\Seeders\System\CriarEspeciesEmpresaAmbientalSeeder;
use Database\Seeders\System\CriarEspeciesEmpresaSerrariaSeeder;
use Illuminate\Support\Facades\Log;

class EmpresaProvisionamentoService
{
    private const CARGOS_CONFIG = [
        'Administrador' => ['*'],
        'Gerente' => ['patio.*', 'dofs.*', 'especies.*', 'usuarios.*', 'cargos.*', 'produtos_dimensionados.*'],
        'Operador' => ['patio.ver', 'patio.editar', 'dofs.ver', 'dofs.criar', 'especies.ver', 'produtos_dimensionados.ver'],
        'Estoquista' => ['patio.*', 'dofs.ver', 'especies.ver', 'produtos_dimensionados.ver'],
    ];

    private const PRODUTOS_DIMENSIONADOS = [
        [
            'tipo_especie' => 'Madeira serrada (caibro)',
            'nome_popular' => 'Maçaranduba',
            'nome_cientifico' => 'Manilkara huberi',
            'espessura_cm' => 2.50,
            'largura_cm' => 5.00,
        ],
        [
            'tipo_especie' => 'Madeira serrada (ripa)',
            'nome_popular' => 'Maçaranduba',
            'nome_cientifico' => 'Manilkara huberi',
            'espessura_cm' => 1.00,
            'largura_cm' => 5.00,
        ],
        [
            'tipo_especie' => 'Madeira serrada (viga)',
            'nome_popular' => 'Maçaranduba',
            'nome_cientifico' => 'Manilkara huberi',
            'espessura_cm' => 5.00,
            'largura_cm' => 10.00,
        ],
    ];

    /**
     * @return array{cargos:int,tipos_serragem:int,especies:int,produtos_dimensionados:int}
     */
    public function provisionarDadosIniciais(Empresa $empresa): array
    {
        $tipoEmpresa = $this->normalizarTipoEmpresa($empresa->tipo_empresa ?? Empresa::TIPO_SERRARIA);

        try {
            return [
                'cargos' => $this->criarCargos(empresa: $empresa),
                'tipos_serragem' => $this->criarTiposSerragem(empresa: $empresa),
                'especies' => $this->criarEspecies(empresa: $empresa, tipoEmpresa: $tipoEmpresa),
                'produtos_dimensionados' => $this->deveCriarProdutosDimensionados($tipoEmpresa)
                    ? $this->criarProdutosDimensionados(empresa: $empresa)
                    : 0,
            ];
        } catch (\Throwable $e) {
            Log::error('Erro ao provisionar dados iniciais da empresa', [
                'empresa_id' => $empresa->id,
                'erro' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function criarCargos(Empresa $empresa): int
    {
        $totalCargosProcessados = 0;

        foreach (self::CARGOS_CONFIG as $nome => $permissoesPattern) {
            $cargo = Cargo::withTrashed()->firstOrNew([
                'empresa_id' => $empresa->id,
                'nome' => $nome,
            ]);

            $cargo->descricao = "Cargo de {$nome}";
            $cargo->save();

            if (method_exists($cargo, 'trashed') && $cargo->trashed()) {
                $cargo->restore();
            }

            $permissaoIds = $this->resolverPermissoes($permissoesPattern);
            $cargo->permissoes()->sync($permissaoIds);
            $totalCargosProcessados++;
        }

        return $totalCargosProcessados;
    }

    private function criarTiposSerragem(Empresa $empresa): int
    {
        return app(TipoSerragemService::class)->criarPadroesParaEmpresa((string) $empresa->id);
    }

    private function criarEspecies(Empresa $empresa, string $tipoEmpresa): int
    {
        $totalEspeciesCriadas = 0;

        if ($this->deveCriarEspeciesSerraria($tipoEmpresa)) {
            $totalEspeciesCriadas += $this->criarEspeciesSerraria($empresa);
        }

        if ($this->deveCriarEspeciesAmbientais($tipoEmpresa)) {
            $totalEspeciesCriadas += $this->criarEspeciesAmbientais($empresa);
        }

        return $totalEspeciesCriadas;
    }

    private function criarEspeciesAmbientais(Empresa $empresa): int
    {
        $totalEspeciesCriadas = 0;

        foreach (CriarEspeciesEmpresaAmbientalSeeder::TIPOS_ESPECIFICOS as $nomeTipo => $especies) {
            foreach ($especies as $dadosEspecie) {
                $tipo = ProdutoDimensionadoEspecieMatcher::tipoDaEspecie($nomeTipo);
                $tipoSerragem = app(TipoSerragemService::class)->obterOuCriarPorNome($tipo, (string) $empresa->id);

                $especie = Especie::firstOrCreate(
                    [
                        'nome_cientifico' => $dadosEspecie['nome_cientifico'],
                        'tipo_serragem_id' => $tipoSerragem->id,
                        'empresa_id' => $empresa->id,
                    ],
                    [
                        'nome_cientifico' => $dadosEspecie['nome_cientifico'],
                        'nome_popular' => $dadosEspecie['nome_popular'],
                        'tipo_serragem_id' => $tipoSerragem->id,
                        'tipo' => $tipo,
                        'nome_tipo' => $nomeTipo,
                        'empresa_id' => $empresa->id,
                    ]
                );

                if ($especie->wasRecentlyCreated) {
                    $totalEspeciesCriadas++;
                }
            }
        }

        return $totalEspeciesCriadas;
    }

    private function criarEspeciesSerraria(Empresa $empresa): int
    {
        $totalEspeciesCriadas = 0;

        foreach (CriarEspeciesEmpresaSerrariaSeeder::TIPOS_BASE as $nomeTipo) {
            foreach (CriarEspeciesEmpresaSerrariaSeeder::ESPECIES as $dadosEspecie) {
                $tipo = ProdutoDimensionadoEspecieMatcher::tipoDaEspecie($nomeTipo);
                $tipoSerragem = app(TipoSerragemService::class)->obterOuCriarPorNome($tipo, (string) $empresa->id);

                $especie = Especie::firstOrCreate(
                    [
                        'nome_cientifico' => $dadosEspecie['nome_cientifico'],
                        'tipo_serragem_id' => $tipoSerragem->id,
                        'empresa_id' => $empresa->id,
                    ],
                    [
                        'nome_cientifico' => $dadosEspecie['nome_cientifico'],
                        'nome_popular' => $dadosEspecie['nome_popular'],
                        'tipo_serragem_id' => $tipoSerragem->id,
                        'tipo' => $tipo,
                        'nome_tipo' => $nomeTipo,
                        'empresa_id' => $empresa->id,
                    ]
                );

                if ($especie->wasRecentlyCreated) {
                    $totalEspeciesCriadas++;
                }
            }
        }

        return $totalEspeciesCriadas;
    }

    private function criarProdutosDimensionados(Empresa $empresa): int
    {
        $totalProdutosCriados = 0;

        foreach (self::PRODUTOS_DIMENSIONADOS as $dadosBase) {
            $tipo = ProdutoDimensionadoEspecieMatcher::tipoDaEspecie($dadosBase['tipo_especie']);
            $tipoNormalizado = ProdutoDimensionadoEspecieMatcher::normalizarTipo($tipo);
            $comprimentosPadrao = $this->resolverComprimentosPorTipo($tipoNormalizado);

            $especieBase = Especie::query()
                ->where('empresa_id', $empresa->id)
                ->where('nome_cientifico', $dadosBase['nome_cientifico'])
                ->where('tipo_serragem_id', app(TipoSerragemService::class)->obterOuCriarPorNome($tipo, (string) $empresa->id)->id)
                ->first();

            if (!$especieBase) {
                continue;
            }

            foreach ($comprimentosPadrao as $comprimentoM) {
                $nomeProduto = $this->formatarNomeProduto(
                    tipo: $tipoNormalizado,
                    nomePopular: $dadosBase['nome_popular'],
                    espessuraCm: (float) $dadosBase['espessura_cm'],
                    larguraCm: (float) $dadosBase['largura_cm'],
                    comprimentoM: $comprimentoM,
                );

                $produto = ProdutoDimensionado::firstOrCreate(
                    [
                        'empresa_id' => $empresa->id,
                        'especie_id' => $especieBase->id,
                        'tipo_dof' => $tipoNormalizado,
                        'espessura_cm' => $dadosBase['espessura_cm'],
                        'largura_cm' => $dadosBase['largura_cm'],
                        'comprimento_m' => $comprimentoM,
                    ],
                    [
                        'nome' => $nomeProduto,
                        'nome_concatenado' => $nomeProduto,
                        'observacao' => null,
                        'ativo' => true,
                    ]
                );

                if ($produto->wasRecentlyCreated) {
                    $this->sincronizarEspeciesVinculadas($produto, $especieBase, $tipoNormalizado);
                    $totalProdutosCriados++;
                }
            }
        }

        return $totalProdutosCriados;
    }

    /**
     * @return array<int, float>
     */
    private function resolverComprimentosPorTipo(string $tipoNormalizado): array
    {
        return match ($tipoNormalizado) {
            'RIPA' => [1.00],
            default => $this->gerarComprimentosPadrao(),
        };
    }

    /**
     * @return array<int, float>
     */
    private function gerarComprimentosPadrao(): array
    {
        $comprimentos = [];

        for ($valor = 1.0; $valor <= 6.0; $valor += 0.5) {
            $comprimentos[] = round($valor, 2);
        }

        return $comprimentos;
    }

    /**
     * @param array<int, string> $patterns
     * @return array<int, string>
     */
    private function resolverPermissoes(array $patterns): array
    {
        $ids = [];

        foreach ($patterns as $pattern) {
            if ($pattern === '*') {
                return Permissao::query()->pluck('id')->all();
            }

            if (str_ends_with($pattern, '.*')) {
                $grupo = str_replace('.*', '', $pattern);
                $grupoIds = Permissao::query()
                    ->where('nome', 'like', "{$grupo}.%")
                    ->pluck('id')
                    ->all();
                $ids = array_merge($ids, $grupoIds);
                continue;
            }

            $permissao = Permissao::query()->where('nome', $pattern)->first();
            if ($permissao) {
                $ids[] = $permissao->id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function formatarNomeProduto(
        string $tipo,
        string $nomePopular,
        float $espessuraCm,
        float $larguraCm,
        float $comprimentoM,
    ): string {
        $nomePopularNormalizado = trim(preg_replace('/\s+/u', ' ', $nomePopular)) ?: 'SEM_NOME_POPULAR';

        return sprintf(
            '%s %s %s(CM) x %s(CM) x %s(ML)',
            $tipo,
            mb_strtoupper($nomePopularNormalizado),
            number_format($espessuraCm, 2, '.', ''),
            number_format($larguraCm, 2, '.', ''),
            number_format($comprimentoM, 2, '.', ''),
        );
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

    /**
     * @return array<int, string>
     */
    private function resolverEspeciesVinculadasIds(Especie $especieBase, string $tipoDof): array
    {
        $nomePopularBase = ProdutoDimensionadoEspecieMatcher::normalizarTexto($especieBase->nome_popular);
        $tipoNormalizado = ProdutoDimensionadoEspecieMatcher::normalizarTipo($tipoDof);

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
            $mesmoPopular = ProdutoDimensionadoEspecieMatcher::normalizarTexto($especie->nome_popular) === $nomePopularBase;
            if (!$mesmoPopular) {
                continue;
            }

            $tipoDaEspecie = ProdutoDimensionadoEspecieMatcher::normalizarTipoEspecie(
                $especie->tipoSerragem?->nome ?? $especie->tipo,
                $especie->nome_tipo,
            );
            if ($tipoDaEspecie !== $tipoNormalizado) {
                continue;
            }

            $ids[] = (string) $especie->id;
        }

        $ids[] = (string) $especieBase->id;

        return array_values(array_unique($ids));
    }

    private function normalizarTipoEmpresa(?string $tipoEmpresa): string
    {
        $valor = strtoupper(trim((string) $tipoEmpresa));

        return in_array($valor, Empresa::TIPOS, true)
            ? $valor
            : Empresa::TIPO_SERRARIA;
    }

    private function deveCriarEspeciesSerraria(string $tipoEmpresa): bool
    {
        return in_array($tipoEmpresa, [Empresa::TIPO_SERRARIA, Empresa::TIPO_MISTA], true);
    }

    private function deveCriarEspeciesAmbientais(string $tipoEmpresa): bool
    {
        return in_array($tipoEmpresa, [Empresa::TIPO_AMBIENTAL, Empresa::TIPO_MISTA], true);
    }

    private function deveCriarProdutosDimensionados(string $tipoEmpresa): bool
    {
        return in_array($tipoEmpresa, [Empresa::TIPO_SERRARIA, Empresa::TIPO_MISTA], true);
    }
}
