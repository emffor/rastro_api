<?php

namespace Database\Seeders;

use App\Models\Especie;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

class EspecieSeeder extends Seeder
{
    public function run(): void
    {
        $especies = [
            ['nome_cientifico' => 'Handroanthus impetiginosus', 'nome_popular' => 'Ipê Roxo', 'tipo' => ''],
            ['nome_cientifico' => 'Handroanthus serratifolius', 'nome_popular' => 'Ipê Amarelo', 'tipo' => ''],
            ['nome_cientifico' => 'Dipteryx odorata', 'nome_popular' => 'Cumaru', 'tipo' => ''],
            ['nome_cientifico' => 'Hymenaea courbaril', 'nome_popular' => 'Jatobá', 'tipo' => ''],
            ['nome_cientifico' => 'Cedrela odorata', 'nome_popular' => 'Cedro', 'tipo' => ''],
            ['nome_cientifico' => 'Swietenia macrophylla', 'nome_popular' => 'Mogno', 'tipo' => ''],
            ['nome_cientifico' => 'Manilkara huberi', 'nome_popular' => 'Maçaranduba', 'tipo' => ''],
            ['nome_cientifico' => 'Dinizia excelsa', 'nome_popular' => 'Angelim Vermelho', 'tipo' => ''],
            ['nome_cientifico' => 'Hymenolobium petraeum', 'nome_popular' => 'Angelim Pedra', 'tipo' => ''],
            ['nome_cientifico' => 'Astronium lecointei', 'nome_popular' => 'Muiracatiara', 'tipo' => ''],
            ['nome_cientifico' => 'Bowdichia nitida', 'nome_popular' => 'Sucupira Preta', 'tipo' => ''],
            ['nome_cientifico' => 'Pterodon emarginatus', 'nome_popular' => 'Sucupira Branca', 'tipo' => ''],
            ['nome_cientifico' => 'Bertholletia excelsa', 'nome_popular' => 'Castanheira', 'tipo' => ''],
            ['nome_cientifico' => 'Carapa guianensis', 'nome_popular' => 'Andiroba', 'tipo' => ''],
            ['nome_cientifico' => 'Copaifera langsdorffii', 'nome_popular' => 'Copaíba', 'tipo' => ''],
            ['nome_cientifico' => 'Mezilaurus itauba', 'nome_popular' => 'Itaúba', 'tipo' => ''],
            ['nome_cientifico' => 'Goupia glabra', 'nome_popular' => 'Cupiúba', 'tipo' => ''],
            ['nome_cientifico' => 'Qualea paraensis', 'nome_popular' => 'Mandioqueira', 'tipo' => ''],
            ['nome_cientifico' => 'Erisma uncinatum', 'nome_popular' => 'Cedrinho', 'tipo' => ''],
            ['nome_cientifico' => 'Vochysia maxima', 'nome_popular' => 'Quaruba', 'tipo' => ''],
            ['nome_cientifico' => 'Tabebuia cassinoides', 'nome_popular' => 'Caixeta', 'tipo' => ''],
            ['nome_cientifico' => 'Peltogyne confertiflora', 'nome_popular' => 'Roxinho', 'tipo' => ''],
            ['nome_cientifico' => 'Apuleia leiocarpa', 'nome_popular' => 'Garapa', 'tipo' => ''],
            ['nome_cientifico' => 'Bagassa guianensis', 'nome_popular' => 'Tatajuba', 'tipo' => ''],
            ['nome_cientifico' => 'Clarisia racemosa', 'nome_popular' => 'Guariúba', 'tipo' => ''],
            ['nome_cientifico' => 'Ocotea porosa', 'nome_popular' => 'Imbuia', 'tipo' => ''],
            ['nome_cientifico' => 'Aspidosperma polyneuron', 'nome_popular' => 'Peroba Rosa', 'tipo' => ''],
            ['nome_cientifico' => 'Cordia goeldiana', 'nome_popular' => 'Freijó', 'tipo' => ''],
            ['nome_cientifico' => 'Parkia pendula', 'nome_popular' => 'Faveira', 'tipo' => ''],
            ['nome_cientifico' => 'Ceiba pentandra', 'nome_popular' => 'Sumaúma', 'tipo' => ''],
        ];

        $empresas = Empresa::all();

        foreach ($empresas as $empresa) {
            foreach ($especies as $especie) {
                Especie::firstOrCreate([
                    'empresa_id' => $empresa->id,
                    'nome_cientifico' => $especie['nome_cientifico'],
                    'tipo' => $especie['tipo'],
                ], $especie);
            }
        }
    }
}
