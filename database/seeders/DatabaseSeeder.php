<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Sistema (ordem importa!)
            System\PermissaoSystemSeeder::class,
            MasterSeeder::class,
            AnexoCategoriaSeeder::class,

            // Dados de teste
            EmpresaSeeder::class,
            System\CriarTiposSerragemEmpresaSeeder::class,
            EspecieSeeder::class,
            DofSeeder::class,
        ]);
    }
}
