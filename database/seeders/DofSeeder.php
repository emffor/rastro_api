<?php

namespace Database\Seeders;

use App\Models\Dof;
use App\Models\DofItem;
use App\Models\Especie;
use App\Models\Empresa;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class DofSeeder extends Seeder
{
    public function run(?string $empresaId = null): void
    {
        $query = Empresa::query();
        
        if ($empresaId) {
            $query->where('id', $empresaId);
        }
        
        $empresas = $query->get();
        $tipos = ['VIGA', 'TÁBUA', 'PRANCHA', 'RIPA', 'CAIBRO', 'SARRAFO', 'TORA'];

        foreach ($empresas as $empresaIndex => $empresa) {
            $especies = Especie::where('empresa_id', $empresa->id)->get();

            // Buscar IDs específicos das espécies e categorias da imagem
            $especieViga = Especie::where('nome_cientifico', 'Manilkara huberi')->first();
            $especieCaibro1 = Especie::where('nome_cientifico', 'Manilkara zuribe')->first();
            $especieCaibro2 = Especie::where('nome_cientifico', 'Manilkara gabirita')->first();
            
            $categoriaLinha = \App\Models\Categoria::where('nome', 'Linha Maçaranduba')->first();
            $categoriaCaibro = \App\Models\Categoria::where('nome', 'Caibro Maçaranduba')->first();

            for ($i = 1; $i <= 1; $i++) {
                // Gera número único com sufixo aleatório para evitar conflitos
                $numero = sprintf('DOF-%s-%04d-%s', date('Y'), ($empresaIndex * 10) + $i, rand(100, 999));

                $dof = Dof::firstOrCreate([
                    'empresa_id' => $empresa->id,
                    'numero' => $numero,
                ], [
                    'serie' => $this->gerarSerieAleatoria(),
                    'data_emissao' => Carbon::now()->subDays(rand(1, 30)),
                    'valido_ate' => Carbon::now()->addMonths(rand(6, 24)),
                    'origem' => 'Ceará',
                    'destino' => 'Fortaleza',
                    'status' => 'ATIVO',
                ]);

                // Criar exatamente os 3 itens da imagem
                $itensEspecificos = [
                    [
                        'especie' => $especieViga,
                        'categoria' => $categoriaLinha,
                        'tipo' => 'VIGA',
                        'quantidade' => 5.00
                    ],
                    [
                        'especie' => $especieCaibro1,
                        'categoria' => $categoriaCaibro,
                        'tipo' => 'CAIBRO',
                        'quantidade' => 4.00
                    ],
                    [
                        'especie' => $especieCaibro2,
                        'categoria' => $categoriaCaibro,
                        'tipo' => 'CAIBRO',
                        'quantidade' => 7.50
                    ]
                ];

                // Criar itens e calcular volume total como soma das quantidades inseridas
                $volumeTotal = 0.0;

                foreach ($itensEspecificos as $item) {
                    if (!$item['especie'] || !$item['categoria']) {
                        continue;
                    }

                    DofItem::firstOrCreate([
                        'dof_id' => $dof->id,
                        'especie_id' => $item['especie']->id,
                        'tipo' => $item['tipo'],
                        'categoria_id' => $item['categoria']->id,
                    ], [
                        'quantidade_autorizada' => $item['quantidade'],
                        'quantidade_disponivel' => $item['quantidade'],
                    ]);

                    $volumeTotal += $item['quantidade'];
                }

                $dof->volume_total = $volumeTotal;
                $dof->save();
            }
        }
    }

    /**
     * Gera uma série aleatória no formato como "SUSME5MS88"
     */
    private function gerarSerieAleatoria(): string
    {
        $consoantes = 'BCDFGHJKLMNPQRSTVWXYZ';
        $numeros = '0123456789';
        
        $parte1 = substr(str_shuffle($consoantes), 0, 3);
        $numero = substr(str_shuffle($numeros), 0, 1);
        $parte2 = substr(str_shuffle($consoantes), 0, 2);
        $numero2 = substr(str_shuffle($numeros), 0, 2);
        
        return strtoupper($parte1 . $numero . $parte2 . $numero2);
    }
}
