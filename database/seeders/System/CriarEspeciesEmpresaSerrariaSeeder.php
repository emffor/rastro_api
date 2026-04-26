<?php

namespace Database\Seeders\System;

use App\Models\Empresa;
use App\Models\Especie;
use App\Services\TipoSerragemService;
use App\Support\ProdutoDimensionadoEspecieMatcher;
use Illuminate\Database\Seeder;

class CriarEspeciesEmpresaSerrariaSeeder extends Seeder
{
    public const TIPOS_BASE = [
        'Madeira serrada (viga)',
        'Madeira serrada (caibro)',
        'Madeira serrada (ripa)',
        'Madeira serrada (tábua)',
    ];

    public const ESPECIES = [
        ['nome_cientifico' => 'Buchenavia tetraphylla', 'nome_popular' => 'Amarelão'],
        ['nome_cientifico' => 'Carapa guianensis', 'nome_popular' => 'Andiroba'],
        ['nome_cientifico' => 'Andira surinamensis', 'nome_popular' => 'Angelim'],
        ['nome_cientifico' => 'Hymenolobium petraeum', 'nome_popular' => 'Angelim'],
        ['nome_cientifico' => 'Vatairea guianensis', 'nome_popular' => 'Angelim-amargoso'],
        ['nome_cientifico' => 'Andira fraxinifolia', 'nome_popular' => 'Angelim-Doce'],
        ['nome_cientifico' => 'Andira anthelmia', 'nome_popular' => 'Angelim-Lombriga'],
        ['nome_cientifico' => 'Dinizia excelsa', 'nome_popular' => 'Angelim-pedra'],
        ['nome_cientifico' => 'Andira fraxinifolia', 'nome_popular' => 'Angelim-vermelho'],
        ['nome_cientifico' => 'Andira inermis', 'nome_popular' => 'Angelim-vermelho'],
        ['nome_cientifico' => 'Andira surinamensis', 'nome_popular' => 'Angelim-vermelho'],
        ['nome_cientifico' => 'Anadenanthera colubrina', 'nome_popular' => 'Angico'],
        ['nome_cientifico' => 'Anadenanthera peregrina', 'nome_popular' => 'Angico'],
        ['nome_cientifico' => 'Parapiptadenia rigida', 'nome_popular' => 'Angico'],
        ['nome_cientifico' => 'Piptadenia paniculata', 'nome_popular' => 'Angico'],
        ['nome_cientifico' => 'Anadenanthera colubrina', 'nome_popular' => 'Angico-Branco'],
        ['nome_cientifico' => 'Anadenanthera peregrina', 'nome_popular' => 'Angico-Cascudo'],
        ['nome_cientifico' => 'Parapiptadenia rigida', 'nome_popular' => 'Angico-Gurucaia'],
        ['nome_cientifico' => 'Leucochloron incuriale', 'nome_popular' => 'Angico-Rajado'],
        ['nome_cientifico' => 'Anadenanthera colubrina', 'nome_popular' => 'Angico-Vermelho'],
        ['nome_cientifico' => 'Rollinia sylvatica', 'nome_popular' => 'Ariticum-da-Mata'],
        ['nome_cientifico' => 'Astronium urundeuva', 'nome_popular' => 'Aroeira'],
        ['nome_cientifico' => 'Garcinia gardneriana', 'nome_popular' => 'Bacupari'],
        ['nome_cientifico' => 'Schinopsis brasiliensis', 'nome_popular' => 'Braúna-do-Sertão'],
        ['nome_cientifico' => 'Nectandra grandiflora', 'nome_popular' => 'Caneleira'],
        ['nome_cientifico' => 'Tabebuia aurea', 'nome_popular' => 'Caraúba'],
        ['nome_cientifico' => 'Roupala cataractarum', 'nome_popular' => 'Carvalho-Verde'],
        ['nome_cientifico' => 'Poincianella pyramidalis', 'nome_popular' => 'Catingueira'],
        ['nome_cientifico' => 'Cedrela lilloi', 'nome_popular' => 'Cedrilho'],
        ['nome_cientifico' => 'Erisma uncinatum', 'nome_popular' => 'Cedrinho'],
        ['nome_cientifico' => 'Scleronema micranthum', 'nome_popular' => 'Cedrinho'],
        ['nome_cientifico' => 'Cedrela fissilis', 'nome_popular' => 'Cedro'],
        ['nome_cientifico' => 'Cedrela odorata', 'nome_popular' => 'Cedro-Vermelho'],
        ['nome_cientifico' => 'Eugenia involucrata', 'nome_popular' => 'Cerejeira'],
        ['nome_cientifico' => 'Copaifera langsdorffii', 'nome_popular' => 'Copaíba'],
        ['nome_cientifico' => 'Amburana cearensis', 'nome_popular' => 'Cumaru'],
        ['nome_cientifico' => 'Dipteryx odorata', 'nome_popular' => 'Cumaru'],
        ['nome_cientifico' => 'Goupia glabra', 'nome_popular' => 'Cupiúba'],
        ['nome_cientifico' => 'Tapirira guianensis', 'nome_popular' => 'Cupiúva'],
        ['nome_cientifico' => 'Eschweilera ovata', 'nome_popular' => 'Embiriba'],
        ['nome_cientifico' => 'Parkia platycephala', 'nome_popular' => 'Faveira'],
        ['nome_cientifico' => 'Apuleia molaris', 'nome_popular' => 'Garapeira'],
        ['nome_cientifico' => 'Planchonella pachycarpa', 'nome_popular' => 'Goiabão'],
        ['nome_cientifico' => 'Dasyphyllum tomentosum', 'nome_popular' => 'Guaiapá'],
        ['nome_cientifico' => 'Chrysophyllum lucentifolium', 'nome_popular' => 'Guajará'],
        ['nome_cientifico' => 'Chrysophyllum venezuelanense', 'nome_popular' => 'Guajará'],
        ['nome_cientifico' => 'Erisma uncinatum', 'nome_popular' => 'Guajará'],
        ['nome_cientifico' => 'Micropholis venulosa', 'nome_popular' => 'Guajará'],
        ['nome_cientifico' => 'Pouteria oppositifolia', 'nome_popular' => 'Guajará-bolacha'],
        ['nome_cientifico' => 'Maytenus robusta', 'nome_popular' => 'Guarapoca'],
        ['nome_cientifico' => 'Commiphora leptophloeos', 'nome_popular' => 'Imburana-de-Espinho'],
        ['nome_cientifico' => 'Tabebuia alba', 'nome_popular' => 'Ipê'],
        ['nome_cientifico' => 'Handroanthus albus', 'nome_popular' => 'Ipê-amarelo'],
        ['nome_cientifico' => 'Tabebuia alba', 'nome_popular' => 'Ipê-Amarelo'],
        ['nome_cientifico' => 'Tabebuia serratifolia', 'nome_popular' => 'Ipê-amarelo'],
        ['nome_cientifico' => 'Tabebuia chrysotricha', 'nome_popular' => 'Ipê-Amarelo-Miúdo'],
        ['nome_cientifico' => 'Handroanthus roseoalbus', 'nome_popular' => 'Ipê-branco'],
        ['nome_cientifico' => 'Zeyheria tuberculosa', 'nome_popular' => 'Ipê-Felpudo'],
        ['nome_cientifico' => 'Tabebuia impetiginosa', 'nome_popular' => 'Ipê-Rosa'],
        ['nome_cientifico' => 'Handroanthus impetiginosus', 'nome_popular' => 'Ipê-roxo'],
        ['nome_cientifico' => 'Tabebuia heptaphylla', 'nome_popular' => 'Ipê-Roxo'],
        ['nome_cientifico' => 'Dalbergia brasiliensis', 'nome_popular' => 'Jacarandá'],
        ['nome_cientifico' => 'Machaerium brasiliense', 'nome_popular' => 'Jacarandá'],
        ['nome_cientifico' => 'Machaerium paraguariense', 'nome_popular' => 'Jacarandá'],
        ['nome_cientifico' => 'Dalbergia nigra', 'nome_popular' => 'Jacarandá-da-Bahia'],
        ['nome_cientifico' => 'Dalbergia miscolobium', 'nome_popular' => 'Jacarandá-do-Cerrado'],
        ['nome_cientifico' => 'Platymiscium floribundum', 'nome_popular' => 'Jacarandá-do-Litoral'],
        ['nome_cientifico' => 'Lecythis lurida', 'nome_popular' => 'Jarana'],
        ['nome_cientifico' => 'Lecythis poiteauii', 'nome_popular' => 'Jarana'],
        ['nome_cientifico' => 'Hymenaea courbaril', 'nome_popular' => 'Jatobá'],
        ['nome_cientifico' => 'Hymenaea courbaril', 'nome_popular' => 'Jatobá'],
        ['nome_cientifico' => 'Hymenaea stigonocarpa', 'nome_popular' => 'Jatobá-do-Cerrado'],
        ['nome_cientifico' => 'Guibourtia hymenaeifolia', 'nome_popular' => 'Jatobá-Mirim'],
        ['nome_cientifico' => 'Cariniana legalis', 'nome_popular' => 'Jequitibá'],
        ['nome_cientifico' => 'Ziziphus joazeiro', 'nome_popular' => 'Juazeiro'],
        ['nome_cientifico' => 'Caesalpinia ferrea', 'nome_popular' => 'Jucá'],
        ['nome_cientifico' => 'Mimosa tenuiflora', 'nome_popular' => 'Jurema-Preta'],
        ['nome_cientifico' => 'Sapium glandulatum', 'nome_popular' => 'Leiteiro'],
        ['nome_cientifico' => 'Cordia trichotoma', 'nome_popular' => 'Louro'],
        ['nome_cientifico' => 'Laurus nobilis', 'nome_popular' => 'Louro'],
        ['nome_cientifico' => 'Ocotea porosa', 'nome_popular' => 'Louro'],
        ['nome_cientifico' => 'Bastardiopsis densiflora', 'nome_popular' => 'Louro-Branco'],
        ['nome_cientifico' => 'Cordia alliodora', 'nome_popular' => 'Louro-Freijó'],
        ['nome_cientifico' => 'Cordia trichotoma', 'nome_popular' => 'Louro-Pardo'],
        ['nome_cientifico' => 'Manilkara amazonica', 'nome_popular' => 'Maçaranduba'],
        ['nome_cientifico' => 'Manilkara bidentata', 'nome_popular' => 'Maçaranduba'],
        ['nome_cientifico' => 'Manilkara huberi', 'nome_popular' => 'Maçaranduba'],
        ['nome_cientifico' => 'Manilkara paraensis', 'nome_popular' => 'Maçaranduba'],
        ['nome_cientifico' => 'Manilkara subsericea', 'nome_popular' => 'Maçaranduba'],
        ['nome_cientifico' => 'Schefflera morototoni', 'nome_popular' => 'Mandiocão'],
        ['nome_cientifico' => 'Manilkara huberi', 'nome_popular' => 'Maparajuba'],
        ['nome_cientifico' => 'Agonandra brasiliensis', 'nome_popular' => 'Marfim'],
        ['nome_cientifico' => 'Miconia sellowiana', 'nome_popular' => 'Mexerico'],
        ['nome_cientifico' => 'Swietenia macrophylla', 'nome_popular' => 'Mogno'],
        ['nome_cientifico' => 'Trattinnickia burserifolia', 'nome_popular' => 'Moregueira'],
        ['nome_cientifico' => 'Bauhinia cheilantha', 'nome_popular' => 'Mororó'],
        ['nome_cientifico' => 'Bauhinia ungulata', 'nome_popular' => 'Mororó'],
        ['nome_cientifico' => 'Astronium lecointei', 'nome_popular' => 'Muiracatiara'],
        ['nome_cientifico' => 'Astronium obliquum', 'nome_popular' => 'Muiracatiara'],
        ['nome_cientifico' => 'Erythrina velutina', 'nome_popular' => 'Mulungu'],
        ['nome_cientifico' => 'Erythrina verna', 'nome_popular' => 'Mulungu-Coral'],
        ['nome_cientifico' => 'Albizia inundata', 'nome_popular' => 'Muquém'],
        ['nome_cientifico' => 'Licania tomentosa', 'nome_popular' => 'Oiti-da-Praia'],
        ['nome_cientifico' => 'Cordia glazioviana', 'nome_popular' => 'Pau-Branco-Louro'],
        ['nome_cientifico' => 'Caesalpinia echinata', 'nome_popular' => 'Pau-Brasil'],
        ['nome_cientifico' => 'Handroanthus serratifolius', 'nome_popular' => 'Pau-d’Arco-Amarelo'],
        ['nome_cientifico' => 'Caryocar villosum', 'nome_popular' => 'Pequiá'],
        ['nome_cientifico' => 'Aspidosperma polyneuron', 'nome_popular' => 'Peroba'],
        ['nome_cientifico' => 'Aspidosperma subincanum', 'nome_popular' => 'Peroba-Guatambu'],
        ['nome_cientifico' => 'Aspidosperma olivaceum', 'nome_popular' => 'Peroba-Vermelha'],
        ['nome_cientifico' => 'Cinnamodendron dinisii', 'nome_popular' => 'Pimenteira'],
        ['nome_cientifico' => 'Abies pectinata', 'nome_popular' => 'Pinheiro'],
        ['nome_cientifico' => 'Araucaria angustifolia', 'nome_popular' => 'Pinheiro-do-Paraná'],
        ['nome_cientifico' => 'Eugenia uniflora', 'nome_popular' => 'Pitangueira'],
        ['nome_cientifico' => 'Tibouchina sellowiana', 'nome_popular' => 'Quaresmeira'],
        ['nome_cientifico' => 'Sideroxylum obtusifolium', 'nome_popular' => 'Quixabeira'],
        ['nome_cientifico' => 'Peltogyne angustiflora', 'nome_popular' => 'Roxinho'],
        ['nome_cientifico' => 'Peltogyne confertiflora', 'nome_popular' => 'Roxinho'],
        ['nome_cientifico' => 'Peltogyne lecointei', 'nome_popular' => 'Roxinho'],
        ['nome_cientifico' => 'Peltogyne paniculata', 'nome_popular' => 'Roxinho'],
        ['nome_cientifico' => 'Mimosa caesalpiniifolia', 'nome_popular' => 'Sabiá'],
        ['nome_cientifico' => 'Pterocarpus rohrii', 'nome_popular' => 'Sangueiro'],
        ['nome_cientifico' => 'Lecythis pisonis', 'nome_popular' => 'Sapucaia'],
        ['nome_cientifico' => 'Lonchocarpus campestris', 'nome_popular' => 'Sapuvinha'],
        ['nome_cientifico' => 'Terminalia australis', 'nome_popular' => 'Sarandi'],
        ['nome_cientifico' => 'Bowdichia nitida', 'nome_popular' => 'Sucupira'],
        ['nome_cientifico' => 'Bowdichia virgilioides', 'nome_popular' => 'Sucupira'],
        ['nome_cientifico' => 'Diplotropis purpurea', 'nome_popular' => 'Sucupira'],
        ['nome_cientifico' => 'Pterodon emarginatus', 'nome_popular' => 'Sucupira'],
        ['nome_cientifico' => 'Pterodon polygalaeflorus', 'nome_popular' => 'Sucupira'],
        ['nome_cientifico' => 'Bowdichia virgiliodes', 'nome_popular' => 'Sucupira'],
        ['nome_cientifico' => 'Pterodon pubescens', 'nome_popular' => 'Sucupira-Branca'],
        ['nome_cientifico' => 'Pterodon emarginatus', 'nome_popular' => 'Sucupira-Lisa'],
        ['nome_cientifico' => 'Piptadenia viridiflora', 'nome_popular' => 'Surucucu'],
        ['nome_cientifico' => 'Vitex megapotamica', 'nome_popular' => 'Tarumã'],
        ['nome_cientifico' => 'Vitex polygama', 'nome_popular' => 'Tarumã-Açu'],
        ['nome_cientifico' => 'Bagassa guianensis', 'nome_popular' => 'Tatajuba'],
        ['nome_cientifico' => 'Couratari guianensis', 'nome_popular' => 'Tauari'],
        ['nome_cientifico' => 'Tachigali myrmecophila', 'nome_popular' => 'Taxi'],
        ['nome_cientifico' => 'Tachigali paniculata', 'nome_popular' => 'Taxi'],
        ['nome_cientifico' => 'Tachigali venusta', 'nome_popular' => 'Taxi'],
        ['nome_cientifico' => 'Sclerolobium paniculatum', 'nome_popular' => 'Taxi-Branco'],
        ['nome_cientifico' => 'Picrasma crenata', 'nome_popular' => 'Tenente-José'],
        ['nome_cientifico' => 'Ateleia glazioveana', 'nome_popular' => 'Timbó'],
        ['nome_cientifico' => 'Enterolobium timbouva', 'nome_popular' => 'Timbaúba'],
        ['nome_cientifico' => 'Abarema brachystachya', 'nome_popular' => 'Timbuva'],
        ['nome_cientifico' => 'Magonia pubescens', 'nome_popular' => 'Tingui'],
        ['nome_cientifico' => 'Virola gardneri', 'nome_popular' => 'Urucuba'],
        ['nome_cientifico' => 'Piptocarpha axillaris', 'nome_popular' => 'Vassourão'],
        ['nome_cientifico' => 'Vernonia discolor', 'nome_popular' => 'Vassourão-Preto'],
        ['nome_cientifico' => 'Dalbergia cearensis', 'nome_popular' => 'Violeta'],
        ['nome_cientifico' => 'Ilex brevicuspis', 'nome_popular' => 'Voadeira'],
    ];

    public function run(?Empresa $empresa = null): void
    {
        if (! $empresa) {
            $this->command->error('Empresa não informada. Execute o CriarEmpresaSeeder antes de rodar o CriarEspeciesEmpresaSerrariaSeeder.');

            return;
        }

        $count = 0;
        foreach (self::TIPOS_BASE as $nomeTipo) {
            foreach (self::ESPECIES as $dadosEspecie) {
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
                    $count++;
                }
            }
        }

        $this->command->info("{$count} novas espécies criadas para a empresa '{$empresa->nome}'.");
    }
}
