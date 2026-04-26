<?php

namespace Database\Seeders;

use App\Models\Empresa;
use Illuminate\Database\Seeder;

class EmpresaSeeder extends Seeder
{
    public function run(): void
    {
        $empresas = [
            ['nome' => 'Madeireira Amazônia', 'cnpj' => '11.111.111/0001-11'],
            ['nome' => 'Madeireira Floresta Verde', 'cnpj' => '33.333.333/0001-33'],
        ];

        foreach ($empresas as $empresa) {
            Empresa::firstOrCreate(['cnpj' => $empresa['cnpj']], $empresa);
        }
    }
}
