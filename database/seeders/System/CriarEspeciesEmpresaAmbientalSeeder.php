<?php

namespace Database\Seeders\System;

use App\Models\Empresa;
use App\Models\Especie;
use App\Services\TipoSerragemService;
use App\Support\ProdutoDimensionadoEspecieMatcher;
use Illuminate\Database\Seeder;

class CriarEspeciesEmpresaAmbientalSeeder extends Seeder
{
    public const TIPOS_ESPECIFICOS = [
        'Madeira serrada (cavaco)' => [
            ['nome_cientifico' => 'Diversos', 'nome_popular' => 'Diversos'],
        ],
        'Madeira serrada (estaca)' => [
            ['nome_cientifico' => 'Mimosa caesalpiniifolia', 'nome_popular' => 'Sabiá'],
        ],
        'Madeira serrada (lenha)' => [
            ['nome_cientifico' => 'Diversos', 'nome_popular' => 'Diversos'],
        ],
    ];

    public function run(?Empresa $empresa = null): void
    {
        if (! $empresa) {
            $this->command->error('Empresa não informada. Execute este seeder a partir do fluxo de provisionamento da empresa.');

            return;
        }

        $count = 0;

        foreach (self::TIPOS_ESPECIFICOS as $nomeTipo => $especies) {
            $tipo = ProdutoDimensionadoEspecieMatcher::tipoDaEspecie($nomeTipo);
            $tipoSerragem = app(TipoSerragemService::class)->obterOuCriarPorNome($tipo, (string) $empresa->id);

            foreach ($especies as $dadosEspecie) {
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
                    $count++;
                }
            }
        }

        $this->command->info("{$count} novas espécies ambientais de madeira serrada criadas para a empresa '{$empresa->nome}'.");
    }
}
