<?php

namespace Database\Seeders\System;

use App\Models\Empresa;
use App\Models\Especie;
use App\Models\ProdutoDimensionado;
use App\Services\TipoSerragemService;
use App\Support\ProdutoDimensionadoEspecieMatcher;
use Illuminate\Database\Seeder;

class CriarProdutosDimensionadosSeeder extends Seeder
{
    public function run(?Empresa $empresa = null): void
    {
        if (! $empresa) {
            $cnpj = '07.805.674/0003-74';
            $this->command->info("Buscando empresa padrão com CNPJ: '{$cnpj}'");
            $empresa = Empresa::where('cnpj', $cnpj)->first();

            if (! $empresa) {
                $this->command->error("Empresa com CNPJ '{$cnpj}' não encontrada. Verifique se o CriarEmpresaSeeder foi executado.");

                return;
            }
        }

        $produtos = [
            [
                'tipo_especie' => 'Madeira serrada (caibro)',
                'nome_popular' => 'Maçaranduba',
                'nome_cientifico' => 'Manilkara huberi',
                'espessura_cm' => 2.50,
                'largura_cm' => 5.00,
                'comprimento_m' => 1.00,
            ],
            [
                'tipo_especie' => 'Madeira serrada (caibro)',
                'nome_popular' => 'Jarana',
                'nome_cientifico' => 'Lecythis chartacea',
                'espessura_cm' => 2.50,
                'largura_cm' => 5.00,
                'comprimento_m' => 1.00,
            ],
            [
                'tipo_especie' => 'Madeira serrada (caibro)',
                'nome_popular' => 'Jarana',
                'nome_cientifico' => 'Lecythis chartacea',
                'espessura_cm' => 2.50,
                'largura_cm' => 5.00,
                'comprimento_m' => 2.00,
            ],
            [
                'tipo_especie' => 'Madeira serrada (ripa)',
                'nome_popular' => 'Maçaranduba',
                'nome_cientifico' => 'Manilkara huberi',
                'espessura_cm' => 1.00,
                'largura_cm' => 5.00,
                'comprimento_m' => 1.00,
            ],
            [
                'tipo_especie' => 'Madeira serrada (ripa)',
                'nome_popular' => 'Jarana',
                'nome_cientifico' => 'Lecythis chartacea',
                'espessura_cm' => 1.00,
                'largura_cm' => 5.00,
                'comprimento_m' => 1.00,
            ],
            [
                'tipo_especie' => 'Madeira serrada (viga)',
                'nome_popular' => 'Maçaranduba',
                'nome_cientifico' => 'Manilkara huberi',
                'espessura_cm' => 5.00,
                'largura_cm' => 10.00,
                'comprimento_m' => 1.00,
            ],
            [
                'tipo_especie' => 'Madeira serrada (viga)',
                'nome_popular' => 'Maçaranduba',
                'nome_cientifico' => 'Manilkara huberi',
                'espessura_cm' => 5.00,
                'largura_cm' => 10.00,
                'comprimento_m' => 2.00,
            ],
            [
                'tipo_especie' => 'Madeira serrada (viga)',
                'nome_popular' => 'Jarana',
                'nome_cientifico' => 'Lecythis chartacea',
                'espessura_cm' => 5.00,
                'largura_cm' => 10.00,
                'comprimento_m' => 1.00,
            ],
            [
                'tipo_especie' => 'Madeira serrada (viga)',
                'nome_popular' => 'Jarana',
                'nome_cientifico' => 'Lecythis chartacea',
                'espessura_cm' => 5.00,
                'largura_cm' => 10.00,
                'comprimento_m' => 2.00,
            ],
        ];

        $count = 0;

        foreach ($produtos as $dados) {
            $tipo = ProdutoDimensionadoEspecieMatcher::tipoDaEspecie($dados['tipo_especie']);
            $tipoNormalizado = ProdutoDimensionadoEspecieMatcher::normalizarTipo($tipo);
            $tipoSerragem = app(TipoSerragemService::class)->obterOuCriarPorNome($tipoNormalizado, (string) $empresa->id);

            // Buscar espécie base na empresa
            $especieBase = Especie::query()
                ->where('empresa_id', $empresa->id)
                ->where('nome_cientifico', $dados['nome_cientifico'])
                ->where('tipo_serragem_id', $tipoSerragem->id)
                ->first();

            if (! $especieBase) {
                $this->command->warn(
                    "Espécie '{$dados['nome_cientifico']}' com tipo '{$dados['tipo_especie']}' não encontrada para a empresa '{$empresa->nome}'. Pulando..."
                );

                continue;
            }

            // Gerar nome do produto
            $nomeProduto = $this->formatarNomeProduto(
                $tipoNormalizado,
                $dados['nome_popular'],
                $dados['espessura_cm'],
                $dados['largura_cm'],
                $dados['comprimento_m'],
            );

            // Criar ou encontrar produto
            $produto = ProdutoDimensionado::firstOrCreate(
                [
                    'empresa_id' => $empresa->id,
                    'especie_id' => $especieBase->id,
                    'tipo_dof' => $tipoNormalizado,
                    'espessura_cm' => $dados['espessura_cm'],
                    'largura_cm' => $dados['largura_cm'],
                    'comprimento_m' => $dados['comprimento_m'],
                ],
                [
                    'nome' => $nomeProduto,
                    'nome_concatenado' => $nomeProduto,
                    'observacao' => null,
                    'ativo' => true,
                ]
            );

            // Vincular espécies automaticamente
            if ($produto->wasRecentlyCreated) {
                $this->sincronizarEspeciesVinculadas($produto, $especieBase, $tipoNormalizado);
                $count++;
            }
        }

        $this->command->info("{$count} novos produtos dimensionados criados para a empresa '{$empresa->nome}'.");
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
            if (! $mesmoPopular) {
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
        $ids = array_values(array_unique($ids));

        return ! empty($ids) ? $ids : [(string) $especieBase->id];
    }
}
