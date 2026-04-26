<?php

namespace Database\Seeders;

use App\Models\Dof;
use App\Models\DofItem;
use App\Models\Especie;
use App\Models\Categoria;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class DofEspecificoSeeder extends Seeder
{
    public function run(): void
    {
        $empresaId = 'a0f4aadf-a9b3-4fd1-ac18-ade61c14ea01';
        
        // IDs das espécies
        $especieViga = 'a0f4aae0-8d2e-42a2-b98f-be2f9c3a79aa'; // Manilkara huberi
        $especieCaibro = 'a0f4aae0-a016-4cb3-ac42-07f41bea29c4'; // Manilkara gabirita
        
        // IDs das categorias
        $categoriaLinha = 'a0f4aae0-6370-4d17-b5fc-91bb78a6bac3'; // Linha Maçaranduba
        $categoriaCaibro = 'a0f4aae0-5689-4973-b836-7a7c19cb3d3e'; // Caibro Maçaranduba

        // Criar o DOF
        $dof = Dof::create([
            'empresa_id' => $empresaId,
            'numero' => 'DOF-2026-0003',
            'serie' => 'ASDJJ23JJD',
            'data_emissao' => Carbon::parse('2025-01-25'),
            'valido_ate' => Carbon::parse('2026-12-29'),
            'origem' => 'Ceará',
            'destino' => 'Fortaleza',
            'status' => 'ATIVO',
        ]);

        // Criar itens com quantidades aleatórias entre 1 e 40
        DofItem::create([
            'dof_id' => $dof->id,
            'especie_id' => $especieViga,
            'categoria_id' => $categoriaLinha,
            'tipo' => 'VIGA',
            'quantidade_autorizada' => rand(100, 4000) / 100, // 1.00 a 40.00 m³
            'quantidade_disponivel' => rand(100, 4000) / 100, // 1.00 a 40.00 m³
        ]);

        DofItem::create([
            'dof_id' => $dof->id,
            'especie_id' => $especieCaibro,
            'categoria_id' => $categoriaCaibro,
            'tipo' => 'CAIBRO',
            'quantidade_autorizada' => rand(100, 4000) / 100, // 1.00 a 40.00 m³
            'quantidade_disponivel' => rand(100, 4000) / 100, // 1.00 a 40.00 m³
        ]);

        $this->command->info('DOF criado com sucesso!');
        $this->command->info("Número: {$dof->numero}");
        $this->command->info("Série: {$dof->serie}");
        $this->command->info("ID: {$dof->id}");
    }
}
